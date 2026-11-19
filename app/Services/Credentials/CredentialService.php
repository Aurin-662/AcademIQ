<?php

namespace App\Services\Credentials;

use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\CredentialDelivery;
use App\Models\CredentialVersion;
use App\Models\IssuanceBatch;
use App\Models\StudentReference;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Processes 1.1, 1.3, 1.4, 1.5.
 * D1 holds commitment + status only; the private package is sealed to the student wallet key and kept
 * server-side only as ciphertext until the wallet acknowledges it.
 */
class CredentialService
{
    public function __construct(private NodeCrypto $node, private IssuerKeyStore $keys, private IssuerAuthorizer $auth) {}

    /** $claims: student_reference, cgpa ("3.52"), programme_code, degree_category, issue_date (Y-m-d), idempotency_key */
    public function issue(User $actor, array $claims): Credential
    {
        $issuer = $this->auth->authorize($actor, 'issue');

        if (! empty($claims['idempotency_key']) && ($dup = Credential::where('idempotency_key', $claims['idempotency_key'])->first())) {
            return $dup; // duplicate submit: never issue twice
        }
        $student = $this->student($claims['student_reference']);
        $credentialId = 'KUET-CRED-'.Str::upper(Str::random(12));
        [$envelope, $commitment, $commitment2] = $this->build($issuer, $student, $credentialId, 1, $claims);

        return DB::connection('mongodb')->transaction(function () use ($issuer, $student, $credentialId, $claims, $commitment, $commitment2, $envelope, $actor) {
            $cred = Credential::create([
                'credential_id' => $credentialId, 'student_reference' => $student->student_reference, 'commitment' => $commitment, 'commitment_v2' => $commitment2,
                'issuer_id' => $issuer->issuer_id, 'issuer_key_id' => $issuer->current_key_id, 'issue_date' => $claims['issue_date'],
                'credential_status' => 'active', 'current_version' => 1, 'revoked_at' => null, 'revocation_reason' => null,
                'idempotency_key' => $claims['idempotency_key'] ?? null,
            ]);
            $this->recordVersion($credentialId, 1, $commitment, $issuer, $claims['issue_date']);
            $this->deliver($credentialId, 1, $student, $envelope);
            AuditLogger::log('credential.issued', 'credential', $credentialId, ['version' => 1, 'issuer_id' => $issuer->issuer_id], $actor);
            Notifier::send($student->user_id, 'credential_issued', 'New credential issued', 'KUET issued credential '.$credentialId.'. Open your wallet to receive it.', route('student.wallet.index'));

            return $cred;
        });
    }

    public function update(User $actor, string $credentialId, array $claims): Credential
    {
        $issuer = $this->auth->authorize($actor, 'update');
        $cred = Credential::where('credential_id', $credentialId)->firstOrFail();
        if ($cred->credential_status !== 'active') {
            throw ValidationException::withMessages(['credential' => 'Only active credentials can be updated.']);
        }
        $student = $this->student($cred->student_reference);
        $old = (int) $cred->current_version;
        $new = $old + 1;
        [$envelope, $commitment, $commitment2] = $this->build($issuer, $student, $credentialId, $new, $claims);

        return DB::connection('mongodb')->transaction(function () use ($cred, $old, $new, $issuer, $student, $claims, $commitment, $commitment2, $envelope, $actor) {
            // Atomic compare-and-set on version + status prevents concurrent double-updates.
            $n = Credential::where('credential_id', $cred->credential_id)->where('current_version', $old)->where('credential_status', 'active')
                ->update(['current_version' => $new, 'commitment' => $commitment, 'commitment_v2' => $commitment2, 'issuer_id' => $issuer->issuer_id, 'issuer_key_id' => $issuer->current_key_id, 'issue_date' => $claims['issue_date'], 'updated_at' => now()]);
            if ($n !== 1) {
                throw ValidationException::withMessages(['credential' => 'Credential changed concurrently; reload and retry.']);
            }
            CredentialVersion::where('credential_id', $cred->credential_id)->where('version', $old)->update(['status' => 'superseded', 'superseded_at' => now()]);
            $this->recordVersion($cred->credential_id, $new, $commitment, $issuer, $claims['issue_date']);
            CredentialDelivery::where('credential_id', $cred->credential_id)->whereNull('delivered_at')->delete(); // undelivered older package is obsolete
            $this->deliver($cred->credential_id, $new, $student, $envelope);
            AuditLogger::log('credential.updated', 'credential', $cred->credential_id, ['from' => $old, 'to' => $new], $actor);
            Notifier::send($student->user_id, 'credential_updated', 'Credential updated', 'A new version of '.$cred->credential_id.' was issued. Open your wallet to receive it.', route('student.wallet.index'));

            return Credential::where('credential_id', $cred->credential_id)->first();
        });
    }

    public function revoke(User $actor, string $credentialId, string $reason): Credential
    {
        $this->auth->authorize($actor, 'revoke');
        $cred = Credential::where('credential_id', $credentialId)->firstOrFail();

        return DB::connection('mongodb')->transaction(function () use ($cred, $reason, $actor) {
            $n = Credential::where('credential_id', $cred->credential_id)->where('credential_status', 'active')
                ->update(['credential_status' => 'revoked', 'revoked_at' => now(), 'revocation_reason' => $reason, 'updated_at' => now()]);
            if ($n !== 1) {
                throw ValidationException::withMessages(['credential' => 'Credential is not active.']);
            }
            CredentialVersion::where('credential_id', $cred->credential_id)->where('status', 'active')->update(['status' => 'revoked']);
            CredentialDelivery::where('credential_id', $cred->credential_id)->whereNull('delivered_at')->delete();
            AuditLogger::log('credential.revoked', 'credential', $cred->credential_id, ['reason' => $reason], $actor);
            Notifier::send(StudentReference::where('student_reference', $cred->student_reference)->value('user_id'), 'credential_revoked', 'Credential revoked', $cred->credential_id.' was revoked by KUET.', route('student.credential', $cred->credential_id));

            return Credential::where('credential_id', $cred->credential_id)->first();
        });
    }

    private function student(string $ref): StudentReference
    {
        $s = StudentReference::where('student_reference', $ref)->where('account_status', 'active')->first();
        if (! $s) {
            throw ValidationException::withMessages(['student_reference' => 'No active student with that reference.']);
        }
        if (empty($s->wallet_public_key)) {
            throw ValidationException::withMessages(['student_reference' => 'This student has not set up a wallet yet. Ask them to open the wallet page first.']);
        }

        return $s;
    }

    /** @return array{0:array,1:string,2:string} sealed envelope, commitment, commitment_v2. The plaintext package never leaves this method. */
    private function build(AuthorizedIssuer $issuer, StudentReference $student, string $credentialId, int $version, array $c): array
    {
        return $this->buildMany($issuer, [[$student, $credentialId, $version, $c]])[0];
    }

    /**
     * Same as build() for many credentials: 3 Node processes total (commit, sign, seal) instead of 3 per credential.
     * @param array<int,array{0:StudentReference,1:string,2:int,3:array}> $items student, credentialId, version, claims
     * @return array<int,array{0:array,1:string,2:string}> sealed envelope, commitment, commitment_v2
     */
    private function buildMany(AuthorizedIssuer $issuer, array $items): array
    {
        $key = collect($issuer->keys)->firstWhere('key_id', $issuer->current_key_id);
        $rows = [];
        foreach ($items as [$student, $credentialId, $version, $c]) {
            $cgpa = FieldEncoder::cgpaX100($c['cgpa']);
            $salt = FieldEncoder::randomSalt();
            $rows[] = [
                'student' => $student, 'c' => $c, 'credentialId' => $credentialId, 'version' => $version, 'cgpa' => $cgpa, 'salt' => $salt,
                'fields' => FieldEncoder::fields($credentialId, $student->student_reference, $cgpa, (int) $c['programme_code'], (int) $c['degree_category'], FieldEncoder::date($c['issue_date']), $version, $salt),
            ];
        }
        $commitments = array_map(fn ($r) => $r['commitment'], $this->node->many(array_map(fn ($r) => ['op' => 'commit', 'fields' => array_map('strval', $r['fields'])], $rows)));
        $sigs = $this->keys->signMany($issuer->current_key_id, $commitments);
        // v2 commitment for circuit "claimset": Poseidon(c8, institution_code, passing_year), signed separately. v1 stays untouched.
        $extra = array_map(fn ($r, $c8) => [$c8, (int) ($r['c']['institution_code'] ?? 1), (int) ($r['c']['passing_year'] ?? substr($r['c']['issue_date'], 0, 4))], $rows, $commitments);
        $commitments2 = array_map(fn ($r) => $r['commitment'], $this->node->many(array_map(fn ($f) => ['op' => 'commit', 'fields' => array_map('strval', $f)], $extra)));
        $sigs2 = $this->keys->signMany($issuer->current_key_id, $commitments2);

        $cmds = [];
        foreach ($rows as $i => $r) {
            $c = $r['c'];
            $package = [
                'schema' => config('academiq.credential_schema'),
                'credential_id' => $r['credentialId'], 'credential_version' => $r['version'], 'student_reference' => $r['student']->student_reference,
                'issue_date' => $c['issue_date'],
                'issuer' => ['issuer_id' => $issuer->issuer_id, 'key_id' => $issuer->current_key_id, 'pub' => [$key['ax'], $key['ay']]],
                'claims' => ['cgpa_x100' => $r['cgpa'], 'programme_code' => (int) $c['programme_code'], 'degree_category' => (int) $c['degree_category']],
                // institution_code + passing_year are bound by commitment_v2 (signed), used by circuit "claimset"; name is display-only.
                'profile' => ['name' => User::find($r['student']->user_id)?->name, 'institution_code' => $extra[$i][1], 'passing_year' => $extra[$i][2], 'committed' => true],
                'salt' => $r['salt'], 'fields' => $r['fields'], 'commitment' => $commitments[$i], 'signature' => $sigs[$i],
                'commitment_v2' => $commitments2[$i], 'signature_v2' => $sigs2[$i],
            ];
            $cmds[] = ['op' => 'seal', 'recipient_pub_b64' => $r['student']->wallet_public_key, 'plaintext' => json_encode($package, JSON_THROW_ON_ERROR)];
        }
        $envelopes = $this->node->many($cmds);

        return array_map(fn ($i) => [$envelopes[$i], $commitments[$i], $commitments2[$i]], array_keys($rows));
    }

    /**
     * Bulk issue (1.3). Rows are pre-validated claims (incl. student_reference). Crypto runs first, then every credential
     * plus the batch record is written in ONE transaction. Rows whose student became ineligible are reported, not fatal.
     * @param array<int,array> $validRows keyed by source row number
     * @param array<int,array{ref:string,errors:string[]}> $invalidRows keyed by source row number (rejected at preview)
     */
    public function issueBatch(User $actor, array $validRows, array $invalidRows, string $batchId): IssuanceBatch
    {
        $issuer = $this->auth->authorize($actor, 'issue_batch');
        $failed = $invalidRows;
        $meta = [];
        foreach ($validRows as $rowNo => $claims) {
            try {
                $student = $this->student($claims['student_reference']);
            } catch (ValidationException $e) {
                $failed[$rowNo] = ['ref' => $claims['student_reference'], 'errors' => array_merge(...array_values($e->errors()))];
                continue;
            }
            $meta[] = [$rowNo, $claims, $student, 'KUET-CRED-'.Str::upper(Str::random(12))];
        }
        $built = $this->buildMany($issuer, array_map(fn ($m) => [$m[2], $m[3], 1, $m[1]], $meta));
        ksort($failed);

        return DB::connection('mongodb')->transaction(function () use ($issuer, $meta, $built, $failed, $batchId, $actor) {
            $commitments = [];
            foreach ($meta as $i => [$rowNo, $claims, $student, $credentialId]) {
                [$envelope, $commitment, $commitment2] = $built[$i];
                $commitments[] = $commitment;
                Credential::create([
                    'credential_id' => $credentialId, 'student_reference' => $student->student_reference, 'commitment' => $commitment, 'commitment_v2' => $commitment2,
                    'issuer_id' => $issuer->issuer_id, 'issuer_key_id' => $issuer->current_key_id, 'issue_date' => $claims['issue_date'],
                    'credential_status' => 'active', 'current_version' => 1, 'revoked_at' => null, 'revocation_reason' => null,
                    'idempotency_key' => $batchId.':'.$rowNo, 'batch_id' => $batchId,
                ]);
                $this->recordVersion($credentialId, 1, $commitment, $issuer, $claims['issue_date']);
                $this->deliver($credentialId, 1, $student, $envelope);
                AuditLogger::log('credential.issued', 'credential', $credentialId, ['version' => 1, 'issuer_id' => $issuer->issuer_id, 'batch_id' => $batchId], $actor);
                Notifier::send($student->user_id, 'credential_issued', 'New credential issued', 'KUET issued credential '.$credentialId.'. Open your wallet to receive it.', route('student.wallet.index'));
            }
            sort($commitments);

            $batch = IssuanceBatch::create([
                'batch_id' => $batchId, 'issuer_id' => $issuer->issuer_id, 'issued_by' => (string) $actor->getKey(),
                'issued_count' => count($meta), 'failed_count' => count($failed),
                'batch_digest' => hash('sha256', implode('|', $commitments)), // sha256 over sorted commitments (no Merkle tree in this codebase)
                'errors' => collect($failed)->map(fn ($f, $row) => ['row' => $row, 'student_id' => $f['ref'], 'errors' => $f['errors']])->values()->all(),
            ]);
            AuditLogger::log('credential.batch_issued', 'issuance_batch', $batchId, ['issued' => count($meta), 'failed' => count($failed), 'issuer_id' => $issuer->issuer_id], $actor);

            return $batch;
        });
    }

    private function recordVersion(string $credentialId, int $v, string $commitment, AuthorizedIssuer $issuer, string $date): void
    {
        CredentialVersion::create(['credential_id' => $credentialId, 'version' => $v, 'commitment' => $commitment, 'issuer_id' => $issuer->issuer_id, 'issue_date' => $date, 'status' => 'active', 'superseded_at' => null]);
    }

    private function deliver(string $credentialId, int $v, StudentReference $s, array $envelope): void
    {
        CredentialDelivery::create(['credential_id' => $credentialId, 'version' => $v, 'student_reference' => $s->student_reference, 'envelope' => $envelope, 'delivered_at' => null]);
    }
}
