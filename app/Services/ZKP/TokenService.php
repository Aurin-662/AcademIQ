<?php

namespace App\Services\ZKP;

use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\VerificationKey;
use App\Models\VerificationToken;
use App\Services\AuditLogger;
use App\Services\Credentials\FieldEncoder;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Phase 2: holder-generated verification tokens for circuit "claimset" v1 (zkp/circuits/claimset.circom).
 * prepare(): student picks claims -> server fixes the public parameters and reserves a token id.
 * submit():  browser returns {proof, publicSignals}; server checks every public signal against the reservation and the
 *            credential record, verifies Groth16, then activates the token. Private inputs never arrive.
 */
class TokenService
{
    public const CIRCUIT = 'claimset';
    public const LADDER = [200, 250, 300, 325, 350, 375, 400]; // cgpa x100, same order as cgpa_ge[0..6]
    public const PENDING_MINUTES = 15;
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    // publicSignals indexes (see circuit header)
    public const I_YEAR = 7, I_INST = 8, I_DEG = 9, I_PROG = 10, I_REVEAL = 11, I_COMMIT = 12, I_AX = 13, I_AY = 14, I_BIND = 15;

    public function __construct(private Groth16Verifier $verifier) {}

    /**
     * @param array $sel {credential_id, expiry_days, cgpa?:{mode:threshold|reveal}, year?:{lo,hi}, institution?:{code}, degree?:{code}, department?:{code}}
     * @return array instruction for the browser prover
     */
    public function prepare(User $student, array $sel): array
    {
        $ref = $this->ref($student);
        $cred = $this->credential($ref, $sel['credential_id']);
        $vk = $this->vk();
        $claims = $this->claims($sel);
        if (! $claims) {
            throw ValidationException::withMessages(['claims' => 'Select at least one claim.']);
        }
        $tokenId = $this->newTokenId();
        $bind = FieldEncoder::fromString($tokenId);
        $pub = $this->publicInputs($sel, $bind);

        VerificationToken::create([
            'token_id' => $tokenId, 'student_reference' => $ref, 'credential_id' => $cred->credential_id, 'commitment_v2' => $cred->commitment_v2,
            'claims' => $claims, 'expected_inputs' => $pub, 'circuit_version' => (int) $vk->circuit_version,
            'expiry_days' => (int) $sel['expiry_days'], 'status' => 'pending', 'expires_at' => null, 'revoked_at' => null,
        ]);

        return [
            'token_id' => $tokenId, 'public_inputs' => $pub, 'circuit_version' => (int) $vk->circuit_version,
            'wasm_url' => asset("zkp/".self::CIRCUIT."_v{$vk->circuit_version}.wasm"), 'zkey_url' => asset("zkp/".self::CIRCUIT."_v{$vk->circuit_version}.zkey"),
        ];
    }

    /** @return array{result:string,message:string,token_id?:string,category?:string} result: OK | FAILED | REJECTED | ERROR */
    public function submit(User $student, string $tokenId, string $credentialId, array $proof, array $signals): array
    {
        $ref = $this->ref($student);
        $t = VerificationToken::where('token_id', $tokenId)->where('student_reference', $ref)->first();
        if (! $t || $t->status !== 'pending' || $t->created_at->lt(now()->subMinutes(self::PENDING_MINUTES)) || $t->credential_id !== $credentialId) {
            return $this->reject($student, $tokenId, 'not_pending', 'This token request is no longer available. Start again.');
        }
        $s = array_map('strval', $signals);
        $cred = Credential::where('credential_id', $t->credential_id)->first();
        if (count($s) !== 27) {
            return $this->reject($student, $tokenId, 'bad_signals', 'Malformed proof.');
        }
        if (! $cred || $cred->credential_status !== 'active' || $s[self::I_COMMIT] !== $cred->commitment_v2 || $t->commitment_v2 !== $cred->commitment_v2) {
            return $this->reject($student, $tokenId, 'credential_not_current', 'Your credential is not active or has a newer version.');
        }
        $issuer = AuthorizedIssuer::where('issuer_id', $cred->issuer_id)->first();
        $key = $issuer ? collect($issuer->keys)->firstWhere('key_id', $cred->issuer_key_id) : null;
        if (! $issuer || $issuer->authorization_status !== 'active' || ! $key || $key['status'] === 'revoked' || $s[self::I_AX] !== $key['ax'] || $s[self::I_AY] !== $key['ay']) {
            return $this->reject($student, $tokenId, 'issuer_not_authorized', 'Issuer key is not valid.');
        }
        foreach ($t->expected_inputs as $i => $v) {
            if ($s[self::I_BIND + $i] !== (string) $v) {
                return $this->reject($student, $tokenId, 'input_mismatch', 'Proof does not match the selected claims.');
            }
        }
        $vk = VerificationKey::where('circuit_id', self::CIRCUIT)->where('circuit_version', (int) $t->circuit_version)->where('status', 'active')->first();
        if (! $vk) {
            return $this->reject($student, $tokenId, 'unknown_circuit', 'Unsupported or retired circuit.');
        }
        // Single-use gate: only one submission moves the token out of pending.
        if (VerificationToken::where('token_id', $tokenId)->where('status', 'pending')->update(['status' => 'verifying']) !== 1) {
            return $this->reject($student, $tokenId, 'duplicate_submission', 'A proof was already submitted for this token.');
        }
        try {
            $ok = $this->verifier->verify($vk->verification_key, $proof, $s);
        } catch (Throwable $e) {
            VerificationToken::where('token_id', $tokenId)->update(['status' => 'pending']); // system error: allow retry
            report($e);

            return ['result' => 'ERROR', 'message' => 'Verification could not be completed. Please try again.'];
        }
        if (! $ok) {
            VerificationToken::where('token_id', $tokenId)->update(['status' => 'failed']);
            AuditLogger::log('token.proof_invalid', 'verification_token', $tokenId, [], $student);

            return ['result' => 'FAILED', 'message' => 'Proof did not verify.', 'category' => 'invalid_proof'];
        }
        // Consent to share is recorded here: generating the token is the student's act of sharing. Revoking the token withdraws it.
        VerificationToken::where('token_id', $tokenId)->update(['status' => 'active', 'proof' => $proof, 'public_signals' => $s,
            'expires_at' => now()->addDays((int) $t->expiry_days), 'consented_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('token.created', 'verification_token', $tokenId, ['credential_id' => $cred->credential_id, 'claims' => array_column($t->claims, 'claim'), 'expiry_days' => (int) $t->expiry_days, 'consent' => 'granted'], $student);

        return ['result' => 'OK', 'message' => 'Token created.', 'token_id' => $tokenId];
    }

    public function revoke(User $student, string $tokenId): VerificationToken
    {
        $t = VerificationToken::where('token_id', $tokenId)->where('student_reference', $this->ref($student))->firstOrFail();
        if (in_array($t->status, ['active', 'pending'], true)) {
            $t->update(['status' => 'revoked', 'revoked_at' => now()]);
            AuditLogger::log('token.revoked', 'verification_token', $tokenId, ['consent' => 'withdrawn'], $student);
        }

        return $t->refresh();
    }

    /** Token ID: 16 chars of a 32-symbol alphabet = 80 random bits, formatted ACQ-XXXX-XXXX-XXXX-XXXX. */
    private function newTokenId(): string
    {
        do {
            $n = gmp_import(random_bytes(10));
            $chars = '';
            for ($i = 0; $i < 16; $i++) {
                $chars = self::ALPHABET[(int) gmp_intval(gmp_mod($n, 32))].$chars;
                $n = gmp_div_q($n, 32);
            }
            $id = 'ACQ-'.implode('-', str_split($chars, 4));
        } while (VerificationToken::where('token_id', $id)->exists());

        return $id;
    }

    /** Public inputs 15..26 (bind, sel_*, rev, year range, expected codes) as decimal strings. */
    private function publicInputs(array $sel, string $bind): array
    {
        $y = $sel['year'] ?? null;

        return [
            $bind,
            isset($sel['cgpa']) ? '1' : '0', $y ? '1' : '0', isset($sel['institution']) ? '1' : '0', isset($sel['degree']) ? '1' : '0', isset($sel['department']) ? '1' : '0',
            ($sel['cgpa']['mode'] ?? '') === 'reveal' ? '1' : '0',
            $y ? (string) (int) $y['lo'] : '0', $y ? (string) (int) $y['hi'] : '0',
            (string) (int) ($sel['institution']['code'] ?? 0), (string) (int) ($sel['degree']['code'] ?? 0), (string) (int) ($sel['department']['code'] ?? 0),
        ];
    }

    /** Human-readable claim list stored on the token (what was proven, never values). */
    private function claims(array $sel): array
    {
        $out = [];
        if (isset($sel['cgpa'])) {
            $out[] = ['claim' => 'cgpa', 'mode' => $sel['cgpa']['mode'] === 'reveal' ? 'reveal' : 'threshold', 'ladder' => array_map(fn ($x) => $x / 100, self::LADDER)];
        }
        if (isset($sel['year'])) {
            $out[] = ['claim' => 'passing_year', 'mode' => 'range', 'lo' => (int) $sel['year']['lo'], 'hi' => (int) $sel['year']['hi']];
        }
        foreach (['institution' => 'institutions', 'degree' => 'degrees', 'department' => 'programmes'] as $k => $table) {
            if (isset($sel[$k])) {
                $out[] = ['claim' => $k, 'mode' => 'equality', 'code' => (int) $sel[$k]['code'], 'label' => config("academiq.$table")[(int) $sel[$k]['code']] ?? '?'];
            }
        }

        return $out;
    }

    private function ref(User $student): string
    {
        return StudentProfile::where('user_id', (string) $student->getKey())->value('student_reference') ?? abort(403);
    }

    private function credential(string $ref, string $id): Credential
    {
        $c = Credential::where('credential_id', $id)->where('student_reference', $ref)->first();
        if (! $c || $c->credential_status !== 'active') {
            throw ValidationException::withMessages(['credential_id' => 'Credential not found or not active.']);
        }
        if (empty($c->commitment_v2)) {
            throw ValidationException::withMessages(['credential_id' => 'This credential predates claim tokens. Ask KUET to re-issue it.']);
        }

        return $c;
    }

    private function vk(): VerificationKey
    {
        return VerificationKey::where('circuit_id', self::CIRCUIT)->where('status', 'active')->orderByDesc('circuit_version')->first()
            ?? throw ValidationException::withMessages(['claims' => 'Claim tokens are not enabled yet (verification key not registered).']);
    }

    private function reject(User $student, string $tokenId, string $category, string $message): array
    {
        AuditLogger::log('token.rejected', 'verification_token', $tokenId, ['category' => $category], $student);

        return ['result' => 'REJECTED', 'message' => $message, 'category' => $category];
    }
}
