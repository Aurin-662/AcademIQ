<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Models\VerificationKey;
use App\Models\VerificationToken;
use App\Services\Credentials\CredentialService;
use App\Services\Credentials\IssuerService;
use App\Services\Verification\TokenVerifier;
use App\Services\ZKP\Groth16Verifier;
use Tests\TestCase;

/**
 * Verifier checks. The Groth16 step is mocked here (real proofs are covered by TokenTest and the circuit tests);
 * everything else (token/credential/issuer gating, criteria logic, logging, access control) runs for real.
 */
class VerifyTest extends TestCase
{
    private User $admin;
    private User $employer;
    private Credential $cred;
    private const ID = 'ACQ-7F3K-92XD-M4QP-W8TH';

    protected function setUp(): void
    {
        parent::setUp();
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test')]);
        $this->admin = $this->makeUser('admin');
        app(IssuerService::class)->authorize($this->admin, 'Dr. Test', 'Controller');
        VerificationKey::create(['circuit_id' => 'claimset', 'circuit_version' => 1, 'proof_system' => 'groth16', 'status' => 'active', 'activated_at' => now(), 'verification_key' => ['dummy' => 1]]);
        $this->employer = $this->makeUser('employer', ['email' => 'emp@example.test']);
        EmployerProfile::create(['user_id' => (string) $this->employer->getKey(), 'company_name' => 'Acme Ltd']);

        $u = $this->makeUser('student', ['email' => 's@example.test']);
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($k)['ec'];
        StudentProfile::create(['user_id' => (string) $u->getKey(), 'student_reference' => 'KUET-V1']);
        StudentReference::create(['student_reference' => 'KUET-V1', 'user_id' => (string) $u->getKey(), 'account_status' => 'active',
            'wallet_public_key' => base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))]);
        $this->cred = app(CredentialService::class)->issue($this->admin, ['student_reference' => 'KUET-V1', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'institution_code' => 1, 'passing_year' => 2025, 'issue_date' => '2026-06-01']);
        $this->proof(true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
        parent::tearDown();
    }

    private function proof(bool $ok): void
    {
        $this->mock(Groth16Verifier::class, fn ($m) => $m->shouldReceive('verify')->andReturn($ok));
    }

    /** Public signals for: cgpa ladder up to 3.5 true, year range 2024..2026 true, institution == 1 true; degree/department/reveal not selected. */
    private function signals(array $o = []): array
    {
        $s = array_fill(0, 27, '0');
        $issuer = AuthorizedIssuer::first();
        $key = collect($issuer->keys)->firstWhere('key_id', $this->cred->issuer_key_id);
        foreach ([0, 1, 2, 3, 4] as $i) { $s[$i] = '1'; }
        $s[7] = '1'; $s[8] = '1';
        $s[12] = $this->cred->commitment_v2; $s[13] = $key['ax']; $s[14] = $key['ay']; $s[15] = '5';
        $s[16] = '1'; $s[17] = '1'; $s[18] = '1';
        $s[22] = '2024'; $s[23] = '2026'; $s[24] = '1';

        return $o + $s;
    }

    private function token(array $o = [], ?array $signals = null): VerificationToken
    {
        return VerificationToken::create($o + ['token_id' => self::ID, 'student_reference' => 'KUET-V1', 'credential_id' => $this->cred->credential_id, 'commitment_v2' => $this->cred->commitment_v2,
            'claims' => [['claim' => 'cgpa', 'mode' => 'threshold'], ['claim' => 'passing_year', 'mode' => 'range', 'lo' => 2024, 'hi' => 2026], ['claim' => 'institution', 'mode' => 'equality', 'code' => 1, 'label' => 'KUET']],
            'circuit_version' => 1, 'status' => 'active', 'proof' => ['pi_a' => []], 'public_signals' => $signals ?? $this->signals(), 'expires_at' => now()->addDays(10), 'revoked_at' => null]);
    }

    private function check(array $form, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->employer)->post('/employer/verify/'.self::ID, $form);
    }

    private function lastLog(): TokenUsageLog
    {
        return TokenUsageLog::orderByDesc('created_at')->firstOrFail();
    }

    // ---- criteria logic (pure) ----

    public function test_evaluate_matrix(): void
    {
        $eval = fn (array $form, array $s = null) => array_column(TokenVerifier::evaluate($s ?? $this->signals(), TokenVerifier::criteria($form)), 'result', 'key');

        $this->assertSame(['cgpa' => 'PASS'], $eval(['cgpa_min' => '3.50']));
        $this->assertSame(['cgpa' => 'FAIL'], $eval(['cgpa_min' => '3.75']));
        $this->assertSame(['cgpa' => 'NOT PROVEN'], $eval(['cgpa_min' => '3.00'], ['16' => '0'] + $this->signals())); // claim not in token

        // revealed value beats the ladder: exact 3.52
        $rev = ['21' => '1', '11' => '352'] + $this->signals();
        $this->assertSame(['cgpa' => 'PASS'], $eval(['cgpa_min' => '3.50'], $rev));
        $this->assertSame(['cgpa' => 'FAIL'], $eval(['cgpa_min' => '3.75'], $rev));

        // year proven in [2024,2026]
        $this->assertSame('PASS', $eval(['year_from' => 2024])['year']);              // year >= 2024
        $this->assertSame('PASS', $eval(['year_from' => 2020, 'year_to' => 2026])['year']);
        $this->assertSame('FAIL', $eval(['year_from' => 2010, 'year_to' => 2020])['year']);   // disjoint
        $this->assertSame('NOT PROVEN', $eval(['year_from' => 2025, 'year_to' => 2025])['year']); // partial overlap
        $off = ['7' => '0'] + $this->signals(); // proven NOT in [2024,2026]
        $this->assertSame('FAIL', $eval(['year_from' => 2024, 'year_to' => 2026], $off)['year']);
    }

    public function test_equality_and_overall(): void
    {
        $eval = fn (array $form, array $s = null) => TokenVerifier::evaluate($s ?? $this->signals(), TokenVerifier::criteria($form));
        $r = array_column($eval(['institution' => 1]), 'result', 'key');
        $this->assertSame('PASS', $r['institution']);
        $this->assertSame('NOT PROVEN', array_column($eval(['degree' => 1]), 'result', 'key')['degree']);   // not selected in token
        // token proves "institution == 1": asking for 99 is impossible but only FAIL if the flag says equal
        $other = ['24' => '2'] + $this->signals();                                                          // proven institution == 2
        $this->assertSame('FAIL', array_column($eval(['institution' => 1], $other), 'result', 'key')['institution']);
        $neq = ['8' => '0', '24' => '2'] + $this->signals();                                                // proven institution != 2
        $this->assertSame('NOT PROVEN', array_column($eval(['institution' => 1], $neq), 'result', 'key')['institution']);

        $this->assertSame('PASS', TokenVerifier::overall([['result' => 'PASS'], ['result' => 'PASS']]));
        $this->assertSame('FAIL', TokenVerifier::overall([['result' => 'PASS'], ['result' => 'FAIL'], ['result' => 'NOT PROVEN']]));
        $this->assertSame('NOT PROVEN', TokenVerifier::overall([['result' => 'PASS'], ['result' => 'NOT PROVEN']]));
    }

    public function test_extract_token_from_text_link_or_qr_payload(): void
    {
        foreach ([self::ID, strtolower(self::ID), ' '.self::ID.' ', 'https://academiq.test/v/'.self::ID, 'https://x.test/v/'.self::ID.'?utm=1'] as $in) {
            $this->assertSame(self::ID, TokenVerifier::extract($in));
        }
        $this->assertNull(TokenVerifier::extract('ACQ-1234'));
        $this->assertNull(TokenVerifier::extract('hello'));
    }

    // ---- full check flow ----

    public function test_acceptance_case_pass_fail_per_criterion_without_cgpa(): void
    {
        $this->token();
        $r = $this->check(['cgpa_min' => '3.50', 'year_from' => 2024, 'institution' => 1]);
        $log = $this->lastLog();
        $r->assertRedirect(route('employer.verify.result', $log->getKey()));
        $this->assertSame('PASS', $log->overall);
        $this->assertSame(['PASS', 'PASS', 'PASS'], array_column($log->results, 'result'));

        $page = $this->actingAs($this->employer)->get(route('employer.verify.result', $log->getKey()))->assertOk()->getContent();
        $this->assertStringNotContainsString('3.52', $page);
        $this->assertStringContainsString('PASS', $page);

        $this->check(['cgpa_min' => '3.75', 'institution' => 1]);
        $this->assertSame('FAIL', $this->lastLog()->overall);
        $this->check(['degree' => 1, 'institution' => 1]);
        $this->assertSame('NOT PROVEN', $this->lastLog()->overall); // wrong claim: token has no degree flag

        $this->assertSame(3, TokenUsageLog::count());
        $this->assertSame(3, AuditLog::where('action', 'token.verified')->count());
        $this->assertSame('Acme Ltd', $this->lastLog()->verifier_name);
    }

    public function test_pdf_report_downloads(): void
    {
        $this->token();
        $this->check(['cgpa_min' => '3.50']);
        $res = $this->actingAs($this->employer)->get(route('employer.verify.pdf', $this->lastLog()->getKey()))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_invalid_tokens_fail_with_reason_and_are_logged(): void
    {
        $cases = [
            'expired' => fn () => $this->token(['expires_at' => now()->subMinute()]),
            'revoked' => fn () => $this->token(['status' => 'revoked']),
            'unknown_token' => fn () => null,
            'credential_revoked' => function () { $this->token(); app(CredentialService::class)->revoke($this->admin, $this->cred->credential_id, 'Issued in error'); },
            'credential_superseded' => function () {
                $this->token();
                app(CredentialService::class)->update($this->admin, $this->cred->credential_id, ['cgpa' => '3.9', 'programme_code' => 1, 'degree_category' => 1, 'passing_year' => 2025, 'issue_date' => '2026-07-01']);
            },
        ];
        foreach ($cases as $reason => $setup) {
            VerificationToken::query()->delete();
            TokenUsageLog::query()->delete();
            if ($reason === 'credential_revoked' || $reason === 'credential_superseded') {
                Credential::query()->delete();
                $this->cred = app(CredentialService::class)->issue($this->admin, ['student_reference' => 'KUET-V1', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'institution_code' => 1, 'passing_year' => 2025, 'issue_date' => '2026-06-01']);
            }
            $setup();
            $this->check(['cgpa_min' => '3.50']);
            $log = $this->lastLog();
            $this->assertFalse($log->valid, $reason);
            $this->assertSame($reason, $log->reason);
            $this->assertSame('INVALID', $log->overall);
        }
        $this->assertTrue(AuditLog::where('action', 'token.verify_failed')->exists());
    }

    public function test_tampered_proof_fails(): void
    {
        $this->token();
        $this->proof(false);
        $this->check(['cgpa_min' => '3.50']);
        $this->assertSame('invalid_proof', $this->lastLog()->reason);
    }

    public function test_signals_not_matching_the_credential_fail(): void
    {
        $this->token([], $this->signals(['12' => '999']));
        $this->check(['cgpa_min' => '3.50']);
        $this->assertSame('issuer_not_authorized', $this->lastLog()->reason);
    }

    public function test_criteria_required_and_validated(): void
    {
        $this->token();
        $this->check([])->assertSessionHasErrors('criteria');
        $this->check(['cgpa_min' => '3.10'])->assertSessionHasErrors('cgpa_min');
        $this->check(['year_from' => 2026, 'year_to' => 2020])->assertSessionHasErrors('year_to');
        $this->assertSame(0, TokenUsageLog::count());
    }

    public function test_access_control_and_link(): void
    {
        $this->token();
        $student = User::where('role', 'student')->first();
        $this->actingAs($student)->get('/employer/verify')->assertForbidden();
        $this->actingAs($student)->get('/v/'.self::ID)->assertForbidden();
        $this->actingAs($this->employer)->get('/v/'.self::ID)->assertRedirect(route('employer.verify.show', self::ID));
        $this->app['auth']->forgetGuards();
        $this->get('/v/'.self::ID)->assertRedirect(route('login'));

        $this->actingAs($this->employer)->post('/employer/verify/open', ['input' => 'https://x.test/v/'.self::ID])->assertRedirect(route('employer.verify.show', self::ID));
        $this->actingAs($this->employer)->post('/employer/verify/open', ['input' => 'nonsense'])->assertSessionHasErrors('input');

        $this->check(['cgpa_min' => '3.50']);
        $id = $this->lastLog()->getKey();
        $other = $this->makeUser('employer', ['email' => 'e2@example.test']);
        $this->actingAs($other)->get(route('employer.verify.result', $id))->assertNotFound();
    }

    public function test_builder_only_offers_proven_claims_and_hides_values(): void
    {
        $this->token([], null);
        VerificationToken::where('token_id', self::ID)->update(['claims' => [['claim' => 'cgpa', 'mode' => 'threshold']]]);
        $page = $this->actingAs($this->employer)->get(route('employer.verify.show', self::ID))->assertOk()->getContent();
        $this->assertStringContainsString('name="cgpa_min"', $page);
        $this->assertStringNotContainsString('name="year_from"', $page);
        $this->assertStringNotContainsString('name="degree"', $page);
        $this->assertStringNotContainsString('3.52', $page);
    }

    public function test_student_sees_usage_log(): void
    {
        $t = $this->token();
        $this->check(['cgpa_min' => '3.50']);
        $student = User::where('role', 'student')->first();
        $this->actingAs($student)->get('/student/tokens/'.self::ID)->assertOk()->assertSee('Acme Ltd')->assertSee('PASS');
    }
}
