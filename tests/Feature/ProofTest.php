<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuthorizedIssuer;
use App\Models\ConsentRecord;
use App\Models\Credential;
use App\Models\ProofResult;
use App\Models\ProofSubmission;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\VerificationKey;
use App\Models\VerificationRequest;
use App\Services\Credentials\CredentialService;
use App\Services\Credentials\FieldEncoder;
use App\Services\Credentials\IssuerService;
use App\Models\CredentialDelivery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProofTest extends TestCase
{
    private User $admin;
    private User $student;
    private \OpenSSLAsymmetricKey $priv;
    private Credential $cred;
    private array $pkg;

    protected function setUp(): void
    {
        parent::setUp();
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test')]);
        $this->admin = $this->makeUser('admin');
        app(IssuerService::class)->authorize($this->admin, 'Dr. Test', 'Controller');
        VerificationKey::create(['circuit_id' => 'credential', 'circuit_version' => 1, 'proof_system' => 'groth16', 'status' => 'active', 'activated_at' => now(),
            'verification_key' => json_decode(file_get_contents(base_path('zkp/verification-keys/credential_v1.vkey.json')), true)]);

        [$this->student, $this->priv] = $this->makeStudent('KUET-P1', 'p1@example.test');
        $this->cred = app(CredentialService::class)->issue($this->admin, ['student_reference' => 'KUET-P1', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-06-01']);
        $this->pkg = $this->openPackage();
        $this->makeRequest('REQ-1');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
        parent::tearDown();
    }

    private function makeStudent(string $ref, string $email): array
    {
        $u = $this->makeUser('student', ['email' => $email]);
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];
        StudentProfile::create(['user_id' => (string) $u->getKey(), 'student_reference' => $ref]);
        StudentReference::create(['student_reference' => $ref, 'user_id' => (string) $u->getKey(), 'account_status' => 'active',
            'wallet_public_key' => base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))]);

        return [$u, $key];
    }

    private function openPackage(): array
    {
        $env = CredentialDelivery::where('credential_id', $this->cred->credential_id)->orderByDesc('version')->first()->envelope;
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').base64_decode($env['epk']);
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64)."-----END PUBLIC KEY-----\n";
        $key = hash_hkdf('sha256', openssl_pkey_derive(openssl_pkey_get_public($pem), $this->priv, 32), 32, 'academiq-delivery-v1', '');
        $ct = base64_decode($env['ct']);

        return json_decode(openssl_decrypt(substr($ct, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($env['iv']), substr($ct, -16)), true);
    }

    private function makeRequest(string $id, array $o = []): VerificationRequest
    {
        $r = VerificationRequest::create(['request_id' => $id, 'employer_id' => 'emp-1', 'student_reference' => 'KUET-P1',
            'requested_checks' => ['academic' => ['claim_type' => 1, 'param' => 300], 'background' => []], 'request_status' => 'in_progress', 'academic_state' => 'pending'] + $o);
        ConsentRecord::create(['request_id' => $id, 'student_id' => (string) $this->student->getKey(), 'decision' => 'approved', 'approved_scope' => ['academic' => true],
            'requested_scope' => ['academic' => true], 'decision_timestamp' => now(), 'expires_at' => now()->addDay()]);

        return $r;
    }

    /** Stands in for the browser prover (same snarkjs call, same artifacts). */
    private function prove(array $pkg, string $requestId, int $claim = 1, int $param = 300): array
    {
        $f = $pkg['fields'];
        $input = ['credential_id' => $f[0], 'student_reference' => $f[1], 'cgpa_x100' => $f[2], 'programme_code' => $f[3], 'degree_category' => $f[4], 'issue_date' => $f[5], 'credential_version' => $f[6], 'salt' => $f[7],
            'R8x' => $pkg['signature']['R8'][0], 'R8y' => $pkg['signature']['R8'][1], 'S' => $pkg['signature']['S'], 'commitment' => $pkg['commitment'], 'Ax' => $pkg['issuer']['pub'][0], 'Ay' => $pkg['issuer']['pub'][1],
            'request_id' => FieldEncoder::fromString($requestId), 'claim_type' => (string) $claim, 'param' => (string) $param];
        $p = new Process(['node', base_path('zkp/tools/prove.mjs')], base_path(), null, json_encode(['input' => $input, 'wasm' => base_path('public/zkp/credential_v1.wasm'), 'zkey' => base_path('public/zkp/credential_v1.zkey')]), 120);
        $p->mustRun();

        return json_decode($p->getOutput(), true);
    }

    private function submit(array $proof, string $requestId = 'REQ-1', ?User $as = null, array $o = [])
    {
        return $this->actingAs($as ?? $this->student)->postJson('/student/proofs', $o + ['request_id' => $requestId, 'credential_id' => $this->cred->credential_id, 'circuit_version' => 1,
            'proof' => $proof['proof'], 'public_signals' => $proof['publicSignals']]);
    }

    public function test_valid_proof_verifies_and_stores_no_private_or_proof_data(): void
    {
        $this->submit($this->prove($this->pkg, 'REQ-1'))->assertOk()->assertJson(['result' => 'TRUE']);

        $res = ProofResult::first();
        $this->assertSame('TRUE', $res->result);
        $this->assertSame('done', VerificationRequest::first()->academic_state);
        $stored = json_encode(ProofResult::all()->toArray()).json_encode(ProofSubmission::all()->toArray()).json_encode(VerificationRequest::all()->toArray());
        foreach (['pi_a', 'pi_b', 'salt', 'R8', 'cgpa', $this->pkg['salt']] as $needle) {
            $this->assertStringNotContainsString($needle, $stored);
        }
        foreach (['proof.submitted', 'proof.verified'] as $a) {
            $this->assertTrue(AuditLog::where('action', $a)->exists(), $a);
        }
        $this->assertStringNotContainsString($this->pkg['salt'], json_encode(AuditLog::all()->toArray()));
    }

    public function test_tampered_proof_is_false(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        $p['proof']['pi_a'][0] = (string) (gmp_intval(gmp_mod(gmp_add($p['proof']['pi_a'][0], 1), 1000000007)) + 5);
        $this->submit($p)->assertOk()->assertJson(['result' => 'FALSE', 'category' => 'invalid_proof']);
        $this->assertSame('FALSE', ProofResult::first()->result);
    }

    public function test_altered_public_signals_are_rejected_or_false(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        $p['publicSignals'][0] = '12345'; // commitment swapped
        $this->submit($p)->assertOk()->assertJson(['result' => 'FALSE']);
    }

    public function test_revoked_credential_is_false(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        app(CredentialService::class)->revoke($this->admin, $this->cred->credential_id, 'Issued in error');
        $this->submit($p)->assertOk()->assertJson(['result' => 'FALSE', 'category' => 'credential_revoked']);
    }

    public function test_superseded_credential_is_false(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1'); // proof over version 1
        app(CredentialService::class)->update($this->admin, $this->cred->credential_id, ['cgpa' => '3.90', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-07-01']);
        $this->submit($p)->assertOk()->assertJson(['result' => 'FALSE', 'category' => 'commitment_not_current']);
    }

    public function test_request_id_mismatch_rejected_without_consuming_request(): void
    {
        $this->makeRequest('REQ-2');
        $wrong = $this->prove($this->pkg, 'REQ-2'); // bound to another request
        $this->submit($wrong, 'REQ-1')->assertForbidden()->assertJson(['result' => 'REJECTED', 'category' => 'request_binding_mismatch']);
        $this->assertSame('pending', VerificationRequest::where('request_id', 'REQ-1')->first()->academic_state);
        $this->submit($this->prove($this->pkg, 'REQ-1'))->assertOk()->assertJson(['result' => 'TRUE']); // still usable
    }

    public function test_claim_mismatch_rejected(): void
    {
        $this->submit($this->prove($this->pkg, 'REQ-1', 1, 200))->assertForbidden()->assertJson(['category' => 'request_binding_mismatch']); // weaker threshold than requested
    }

    public function test_duplicate_submission_rejected(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        $this->submit($p)->assertOk();
        $this->submit($p)->assertForbidden()->assertJson(['result' => 'REJECTED']);
        VerificationRequest::where('request_id', 'REQ-1')->update(['academic_state' => 'verifying']); // another submission in flight
        $this->submit($p)->assertForbidden()->assertJson(['result' => 'REJECTED']);
        $this->assertSame(1, ProofResult::count());
        $this->assertSame(1, ProofSubmission::count());
    }

    public function test_unauthorized_submissions_rejected_without_verification(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        [$other] = $this->makeStudent('KUET-P2', 'p2@example.test');
        $this->submit($p, 'REQ-1', $other)->assertForbidden()->assertJson(['category' => 'not_authorized']);
        $this->submit($p, 'REQ-NOPE')->assertForbidden();
        $this->actingAs($this->makeUser('employer', ['email' => 'e@example.test']))->postJson('/student/proofs', [])->assertForbidden();

        ConsentRecord::where('request_id', 'REQ-1')->update(['decision' => 'rejected']);
        $this->submit($p)->assertForbidden()->assertJson(['category' => 'not_authorized']);
        ConsentRecord::where('request_id', 'REQ-1')->update(['decision' => 'approved', 'expires_at' => now()->subMinute()]);
        $this->submit($p)->assertForbidden();
        ConsentRecord::where('request_id', 'REQ-1')->update(['expires_at' => now()->addDay(), 'approved_scope' => ['academic' => false]]);
        $this->submit($p)->assertForbidden();
        ConsentRecord::where('request_id', 'REQ-1')->update(['approved_scope' => ['academic' => true]]);
        VerificationRequest::where('request_id', 'REQ-1')->update(['request_status' => 'awaiting_consent']);
        $this->submit($p)->assertForbidden();

        $this->assertSame(0, ProofResult::count());
        $this->assertSame(0, ProofSubmission::count());
        $this->assertTrue(AuditLog::where('action', 'proof.rejected')->exists());
    }

    public function test_other_students_credential_and_unknown_circuit_rejected(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        $this->submit($p, 'REQ-1', null, ['credential_id' => 'KUET-CRED-NOPE'])->assertForbidden()->assertJson(['category' => 'credential_not_registered']);
        $this->submit($p, 'REQ-1', null, ['circuit_version' => 7])->assertForbidden()->assertJson(['category' => 'unknown_circuit']);
        VerificationKey::where('circuit_version', 1)->update(['status' => 'retired']);
        $this->submit($p)->assertForbidden()->assertJson(['category' => 'unknown_circuit']);
    }

    public function test_issuer_not_authorized_is_false(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        AuthorizedIssuer::first()->update(['authorization_status' => 'suspended']);
        $this->submit($p)->assertOk()->assertJson(['result' => 'FALSE', 'category' => 'issuer_not_authorized']);
    }

    public function test_malformed_and_private_payloads_rejected(): void
    {
        $p = $this->prove($this->pkg, 'REQ-1');
        $this->submit(['proof' => $p['proof'], 'publicSignals' => ['1', '2']])->assertStatus(422);
        $this->submit($p, 'REQ-1', null, ['salt' => $this->pkg['salt']])->assertStatus(422);
        $this->submit($p, 'REQ-1', null, ['fields' => $this->pkg['fields']])->assertStatus(422);
        $this->assertSame('pending', VerificationRequest::first()->academic_state);
    }

    public function test_instruction_only_for_authorized_owner(): void
    {
        $this->actingAs($this->student)->getJson('/student/verify/REQ-1/instruction')->assertOk()->assertJsonStructure(['request_id', 'request_id_field', 'claim_type', 'param', 'circuit_version', 'wasm_url', 'zkey_url']);
        [$other] = $this->makeStudent('KUET-P3', 'p3@example.test');
        $this->actingAs($other)->getJson('/student/verify/REQ-1/instruction')->assertNotFound();
        $this->actingAs($this->student)->get('/student/verify/REQ-1')->assertOk();
        $this->actingAs($other)->get('/student/verify/REQ-1')->assertNotFound();
    }
}
