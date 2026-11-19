<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Credential;
use App\Models\CredentialDelivery;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\VerificationKey;
use App\Models\VerificationToken;
use App\Services\Credentials\CredentialService;
use App\Services\Credentials\IssuerService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Needs the claimset circuit built: sh zkp/build.sh claimset 1 */
class TokenTest extends TestCase
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
        VerificationKey::create(['circuit_id' => 'claimset', 'circuit_version' => 1, 'proof_system' => 'groth16', 'status' => 'active', 'activated_at' => now(),
            'verification_key' => json_decode(file_get_contents(base_path('zkp/verification-keys/claimset_v1.vkey.json')), true)]);

        $this->student = $this->makeUser('student', ['email' => 't1@example.test']);
        $this->priv = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($this->priv)['ec'];
        StudentProfile::create(['user_id' => (string) $this->student->getKey(), 'student_reference' => 'KUET-T1']);
        StudentReference::create(['student_reference' => 'KUET-T1', 'user_id' => (string) $this->student->getKey(), 'account_status' => 'active',
            'wallet_public_key' => base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))]);
        $this->cred = app(CredentialService::class)->issue($this->admin, ['student_reference' => 'KUET-T1', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'institution_code' => 1, 'passing_year' => 2025, 'issue_date' => '2026-06-01']);
        $this->pkg = $this->openPackage();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
        parent::tearDown();
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

    private function selection(array $o = []): array
    {
        return $o + ['credential_id' => $this->cred->credential_id, 'expiry_days' => 30, 'cgpa' => ['mode' => 'threshold'], 'year' => ['lo' => 2024, 'hi' => 2026], 'institution' => ['code' => 1]];
    }

    private function prepare(array $sel): array
    {
        return $this->actingAs($this->student)->postJson('/student/tokens/prepare', $sel)->assertOk()->json();
    }

    /** Stands in for the browser prover (same snarkjs call, same artifacts). */
    private function prove(array $instr, ?array $pkg = null): array
    {
        $pkg ??= $this->pkg;
        $f = $pkg['fields'];
        $names = ['bind', 'sel_cgpa', 'sel_year', 'sel_inst', 'sel_deg', 'sel_prog', 'rev_cgpa', 'year_lo', 'year_hi', 'inst_expected', 'deg_expected', 'prog_expected'];
        $input = ['credential_id' => $f[0], 'student_reference' => $f[1], 'cgpa_x100' => $f[2], 'programme_code' => $f[3], 'degree_category' => $f[4], 'issue_date' => $f[5], 'credential_version' => $f[6], 'salt' => $f[7],
            'institution_code' => (string) $pkg['profile']['institution_code'], 'passing_year' => (string) $pkg['profile']['passing_year'],
            'R8x' => $pkg['signature_v2']['R8'][0], 'R8y' => $pkg['signature_v2']['R8'][1], 'S' => $pkg['signature_v2']['S'],
            'commitment_v2' => $pkg['commitment_v2'], 'Ax' => $pkg['issuer']['pub'][0], 'Ay' => $pkg['issuer']['pub'][1]] + array_combine($names, $instr['public_inputs']);
        $p = new Process(['node', base_path('zkp/tools/prove.mjs')], base_path(), null, json_encode(['input' => $input, 'wasm' => base_path('public/zkp/claimset_v1.wasm'), 'zkey' => base_path('public/zkp/claimset_v1.zkey')]), 120);
        $p->mustRun();

        return json_decode($p->getOutput(), true);
    }

    private function submit(array $instr, array $proof, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->student)->postJson('/student/tokens/submit', ['token_id' => $instr['token_id'], 'credential_id' => $this->cred->credential_id,
            'proof' => $proof['proof'], 'public_signals' => $proof['publicSignals']]);
    }

    public function test_valid_token_is_created_with_80_bit_id_and_stores_no_private_data(): void
    {
        $instr = $this->prepare($this->selection());
        $this->assertMatchesRegularExpression('/^ACQ(-[0-9A-Z]{4}){4}$/', $instr['token_id']);
        $this->submit($instr, $this->prove($instr))->assertOk()->assertJson(['result' => 'OK']);

        $t = VerificationToken::first();
        $this->assertSame('active', $t->status);
        $this->assertTrue($t->isUsable());
        $this->assertEqualsWithDelta(30, now()->diffInDays($t->expires_at), 1);
        $s = $t->public_signals;
        $this->assertSame(['1', '1', '1', '1', '1', '0', '0'], array_slice($s, 0, 7)); // 3.52 clears 2.0..3.5
        $this->assertSame(['1', '1', '0', '0', '0'], array_slice($s, 7, 5));            // year ok, institution ok, rest unselected/unrevealed

        $stored = json_encode(VerificationToken::all()->toArray()).json_encode(AuditLog::all()->toArray());
        foreach ([$this->pkg['salt'], 'R8', 'cgpa_x100', '3.52'] as $needle) {
            $this->assertStringNotContainsString($needle, $stored);
        }
        $this->assertTrue(AuditLog::where('action', 'token.created')->exists());
    }

    public function test_tampered_proof_is_rejected(): void
    {
        $instr = $this->prepare($this->selection());
        $p = $this->prove($instr);
        $p['proof']['pi_a'][0] = (string) (gmp_intval(gmp_mod(gmp_add($p['proof']['pi_a'][0], 1), 1000000007)) + 5);
        $this->submit($instr, $p)->assertStatus(422)->assertJson(['result' => 'FAILED']);
        $this->assertSame('failed', VerificationToken::first()->status);
    }

    public function test_proof_for_other_claims_than_selected_is_rejected(): void
    {
        $instr = $this->prepare($this->selection());
        $other = $this->prepare($this->selection(['year' => ['lo' => 2000, 'hi' => 2001]]));
        $proofOfOther = $this->prove($other);
        // wrong claim: signals carry the other token's bind/year range
        $this->submit($instr, $proofOfOther)->assertStatus(422)->assertJson(['category' => 'input_mismatch']);
        $this->assertSame('pending', VerificationToken::where('token_id', $instr['token_id'])->first()->status);
    }

    public function test_revoked_credential_cannot_get_a_token(): void
    {
        $instr = $this->prepare($this->selection());
        $p = $this->prove($instr);
        app(CredentialService::class)->revoke($this->admin, $this->cred->credential_id, 'Issued in error');
        $this->submit($instr, $p)->assertStatus(422)->assertJson(['category' => 'credential_not_current']);
        $this->actingAs($this->student)->postJson('/student/tokens/prepare', $this->selection())->assertStatus(422); // cannot even prepare
    }

    public function test_pending_token_is_single_use(): void
    {
        $instr = $this->prepare($this->selection());
        $p = $this->prove($instr);
        $this->submit($instr, $p)->assertOk();
        $this->submit($instr, $p)->assertStatus(422)->assertJson(['category' => 'not_pending']);
    }

    public function test_other_student_cannot_submit_and_expiry_and_revoke_work(): void
    {
        $instr = $this->prepare($this->selection());
        $p = $this->prove($instr);
        $other = $this->makeUser('student', ['email' => 'other@example.test']);
        StudentProfile::create(['user_id' => (string) $other->getKey(), 'student_reference' => 'KUET-T2']);
        $this->submit($instr, $p, $other)->assertStatus(422)->assertJson(['category' => 'not_pending']);

        $this->submit($instr, $p)->assertOk();
        $t = VerificationToken::first();
        $t->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($t->fresh()->isUsable()); // expired

        $t->update(['expires_at' => now()->addDay()]);
        $this->actingAs($this->student)->post("/student/tokens/{$t->token_id}/revoke")->assertRedirect();
        $this->assertSame('revoked', $t->fresh()->status);
        $this->assertFalse($t->fresh()->isUsable());
        $this->assertTrue(AuditLog::where('action', 'token.revoked')->exists());
    }

    public function test_selection_validation(): void
    {
        $this->actingAs($this->student)->postJson('/student/tokens/prepare', ['credential_id' => $this->cred->credential_id, 'expiry_days' => 30])->assertStatus(422); // nothing selected
        $this->actingAs($this->student)->postJson('/student/tokens/prepare', $this->selection(['year' => ['lo' => 2026, 'hi' => 2020]]))->assertStatus(422);
        $this->actingAs($this->student)->postJson('/student/tokens/prepare', $this->selection(['institution' => ['code' => 99]]))->assertStatus(422);
        $this->actingAs($this->student)->postJson('/student/tokens/prepare', $this->selection(['expiry_days' => 9999]))->assertStatus(422);
    }
}
