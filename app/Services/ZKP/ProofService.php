<?php

namespace App\Services\ZKP;

use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\ConsentRecord;
use App\Models\ProofResult;
use App\Models\ProofSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\VerificationKey;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Services\Credentials\FieldEncoder;
use Throwable;

/**
 * Processes 2.3 (receive proof + public inputs), 2.4 (verify), 2.5 (return result).
 * The backend only ever sees the Groth16 proof and public signals. Private inputs never arrive.
 * Public signal order: [commitment, Ax, Ay, request_id, claim_type, param]  (see zkp/circuits/credential.circom)
 */
class ProofService
{
    public const READY = 'pending';

    public function __construct(private Groth16Verifier $verifier) {}

    /** Gateway-authorized instruction for the holder, or null if the request is not eligible for this student. */
    public function instruction(User $student, string $requestId): ?array
    {
        $req = $this->eligibleRequest($student, $requestId);
        if (! $req) {
            return null;
        }
        $claim = $req->requested_checks['academic'];
        $vk = VerificationKey::where('circuit_id', 'credential')->where('status', 'active')->orderByDesc('circuit_version')->first();
        if (! $vk) {
            return null;
        }
        $v = (int) $vk->circuit_version;

        return [
            'request_id' => $req->request_id,
            'request_id_field' => FieldEncoder::fromString($req->request_id),
            'claim_type' => (int) $claim['claim_type'], 'param' => (int) $claim['param'],
            'circuit_version' => $v,
            'wasm_url' => asset("zkp/credential_v{$v}.wasm"), 'zkey_url' => asset("zkp/credential_v{$v}.zkey"),
        ];
    }

    /** @return array{result:string, message:string, category?:string} result: TRUE | FALSE | REJECTED | ERROR */
    public function submit(User $student, array $p): array
    {
        $requestId = $p['request_id'];
        $req = $this->eligibleRequest($student, $requestId);
        if (! $req) {
            return $this->reject($student, $requestId, 'not_authorized', 'This request is not available for proof submission.');
        }
        $claim = $req->requested_checks['academic'];

        $vk = VerificationKey::where('circuit_id', 'credential')->where('circuit_version', (int) $p['circuit_version'])->where('status', 'active')->first();
        if (! $vk) {
            return $this->reject($student, $requestId, 'unknown_circuit', 'Unsupported or retired circuit version.');
        }
        $cred = Credential::where('credential_id', $p['credential_id'])->first();
        if (! $cred || $cred->student_reference !== $req->student_reference) {
            return $this->reject($student, $requestId, 'credential_not_registered', 'Credential not found for this applicant.');
        }
        $s = array_map('strval', $p['public_signals']);
        $expected = [3 => FieldEncoder::fromString($requestId), 4 => (string) (int) $claim['claim_type'], 5 => (string) (int) $claim['param']];
        foreach ($expected as $i => $val) {
            if ($s[$i] !== $val) {
                return $this->reject($student, $requestId, 'request_binding_mismatch', 'Proof is not bound to this request or claim.');
            }
        }

        // Single-use gate. Atomic: exactly one submission can move the request out of READY.
        $won = VerificationRequest::where('request_id', $requestId)->where('request_status', 'in_progress')->where('academic_state', self::READY)->update(['academic_state' => 'verifying']);
        if ($won !== 1) {
            return $this->reject($student, $requestId, 'duplicate_submission', 'A proof was already submitted for this request.');
        }
        AuditLogger::log('proof.submitted', 'verification_request', $requestId, ['credential_id' => $cred->credential_id, 'circuit_version' => $vk->circuit_version], $student);
        ProofSubmission::create(['request_id' => $requestId, 'credential_id' => $cred->credential_id, 'circuit_version' => (int) $vk->circuit_version, 'submitted_at' => now()]);

        try {
            [$ok, $category] = $this->evaluate($cred, $vk, $p['proof'], $s);
        } catch (Throwable $e) {
            VerificationRequest::where('request_id', $requestId)->update(['academic_state' => self::READY]); // system error: allow retry
            AuditLogger::log('proof.error', 'verification_request', $requestId, [], $student);
            report($e);

            return ['result' => 'ERROR', 'message' => 'Verification could not be completed. Please try again.'];
        }

        $result = $ok ? 'TRUE' : 'FALSE';
        ProofResult::create(['request_id' => $requestId, 'credential_id' => $cred->credential_id, 'result' => $result, 'circuit_version' => (int) $vk->circuit_version, 'verification_timestamp' => now(), 'failure_category' => $category]);
        VerificationRequest::where('request_id', $requestId)->update(['academic_state' => 'done', 'academic_result' => $result]);
        app(\App\Services\Verification\RequestCollector::class)->refresh($requestId);
        AuditLogger::log($ok ? 'proof.verified' : 'proof.failed', 'verification_request', $requestId, ['result' => $result, 'category' => $category], $student);
        Notifier::send($req->employer_id, $ok ? 'proof_verified' : 'proof_failed', $ok ? 'Academic claim verified' : 'Academic claim not verified', 'Request '.$requestId, route('employer.requests.show', $requestId));
        Notifier::send((string) $student->getKey(), $ok ? 'proof_verified' : 'proof_failed', $ok ? 'Your proof was verified' : 'Your proof failed verification', 'Request '.$requestId, route('student.requests.show', $requestId));

        return ['result' => $result, 'message' => $ok ? 'Claim verified.' : 'Verification failed.', 'category' => $category];
    }

    /** @return array{0:bool,1:?string} */
    private function evaluate(Credential $cred, VerificationKey $vk, array $proof, array $s): array
    {
        if ($cred->credential_status === 'revoked') {
            return [false, 'credential_revoked'];
        }
        if ($cred->credential_status !== 'active') {
            return [false, 'credential_inactive'];
        }
        if ($s[0] !== $cred->commitment) {
            return [false, 'commitment_not_current']; // superseded or unknown commitment
        }
        $issuer = AuthorizedIssuer::where('issuer_id', $cred->issuer_id)->first();
        $key = $issuer ? collect($issuer->keys)->firstWhere('key_id', $cred->issuer_key_id) : null;
        if (! $issuer || $issuer->authorization_status !== 'active' || ! $key || $key['status'] === 'revoked') {
            return [false, 'issuer_not_authorized'];
        }
        if ($s[1] !== $key['ax'] || $s[2] !== $key['ay']) {
            return [false, 'issuer_key_mismatch'];
        }

        return $this->verifier->verify($vk->verification_key, $proof, $s) ? [true, null] : [false, 'invalid_proof'];
    }

    /** Request belongs to the student, consent approved and unexpired, gateway routed it for academic verification. */
    private function eligibleRequest(User $student, string $requestId): ?VerificationRequest
    {
        $ref = StudentProfile::where('user_id', (string) $student->getKey())->value('student_reference');
        $req = VerificationRequest::where('request_id', $requestId)->first();
        if (! $ref || ! $req || $req->student_reference !== $ref || $req->request_status !== 'in_progress' || $req->academic_state !== self::READY || empty($req->requested_checks['academic'])) {
            return null;
        }
        $consent = ConsentRecord::where('request_id', $requestId)->where('decision', 'approved')->where('approved_scope.academic', true)->whereNull('revoked_at')->first();
        if (! $consent || ($consent->expires_at && $consent->expires_at->isPast())) {
            return null;
        }

        return $req;
    }

    private function reject(User $student, string $requestId, string $category, string $message): array
    {
        AuditLogger::log('proof.rejected', 'verification_request', $requestId, ['category' => $category], $student);

        return ['result' => 'REJECTED', 'message' => $message, 'category' => $category];
    }
}
