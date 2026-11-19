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
use App\Models\VerificationBatch;
use App\Models\VerificationBatchItem;
use App\Models\VerificationKey;
use App\Models\VerificationToken;
use App\Services\Credentials\CredentialService;
use App\Services\Credentials\IssuerService;
use App\Services\Verification\BatchVerifier;
use App\Services\ZKP\Groth16Verifier;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Bulk verification. Groth16 is mocked (see VerifyTest); batching, gating, limits, exports and access control run for real. */
class BulkVerifyTest extends TestCase
{
    private User $admin;
    private User $employer;
    private Credential $cred;
    private const T1 = 'ACQ-AAAA-AAAA-AAAA-AAAA', T2 = 'ACQ-BBBB-BBBB-BBBB-BBBB', T3 = 'ACQ-CCCC-CCCC-CCCC-CCCC', T4 = 'ACQ-DDDD-DDDD-DDDD-DDDD', T5 = 'ACQ-EEEE-EEEE-EEEE-EEEE';

    protected function setUp(): void
    {
        parent::setUp();
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test'), 'queue.default' => 'sync']);
        $this->admin = $this->makeUser('admin');
        app(IssuerService::class)->authorize($this->admin, 'Dr. Test', 'Controller');
        VerificationKey::create(['circuit_id' => 'claimset', 'circuit_version' => 1, 'proof_system' => 'groth16', 'status' => 'active', 'activated_at' => now(), 'verification_key' => ['dummy' => 1]]);
        $this->employer = $this->makeUser('employer', ['email' => 'emp@example.test']);
        EmployerProfile::create(['user_id' => (string) $this->employer->getKey(), 'company_name' => 'Acme Ltd']);
        $u = $this->makeUser('student', ['email' => 's@example.test']);
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($k)['ec'];
        StudentProfile::create(['user_id' => (string) $u->getKey(), 'student_reference' => 'KUET-B1']);
        StudentReference::create(['student_reference' => 'KUET-B1', 'user_id' => (string) $u->getKey(), 'account_status' => 'active',
            'wallet_public_key' => base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))]);
        $this->cred = app(CredentialService::class)->issue($this->admin, ['student_reference' => 'KUET-B1', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'institution_code' => 1, 'passing_year' => 2025, 'issue_date' => '2026-06-01']);
        $this->mock(Groth16Verifier::class, fn ($m) => $m->shouldReceive('verify')->andReturn(true));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
        parent::tearDown();
    }

    /** ladder: how many ladder flags are 1 (5 = up to 3.5). Claims in token: cgpa only. */
    private function token(string $id, int $ladder = 5, array $o = []): VerificationToken
    {
        $key = collect(AuthorizedIssuer::first()->keys)->firstWhere('key_id', $this->cred->issuer_key_id);
        $s = array_fill(0, 27, '0');
        for ($i = 0; $i < $ladder; $i++) { $s[$i] = '1'; }
        $s[12] = $this->cred->commitment_v2; $s[13] = $key['ax']; $s[14] = $key['ay']; $s[16] = '1';

        return VerificationToken::create($o + ['token_id' => $id, 'student_reference' => 'KUET-B1', 'credential_id' => $this->cred->credential_id, 'commitment_v2' => $this->cred->commitment_v2,
            'claims' => [['claim' => 'cgpa', 'mode' => 'threshold']], 'circuit_version' => 1, 'status' => 'active', 'proof' => ['pi_a' => []], 'public_signals' => $s, 'expires_at' => now()->addDays(5), 'revoked_at' => null]);
    }

    private function seedMixed(): void
    {
        $this->token(self::T1);                                   // PASS at >= 3.50
        $this->token(self::T2, 3);                                // flags only to 3.0 -> FAIL
        $this->token(self::T3, 5, ['expires_at' => now()->subMinute()]); // expired
        $this->token(self::T4, 5, ['status' => 'revoked']);       // revoked
        // T5: unknown
    }

    private function mixedList(): string
    {
        return implode("\n", [self::T1.', Alice, CV-1', self::T2.', Bob', self::T3, self::T4, self::T5, 'garbage-text', self::T1.', Alice again']);
    }

    private function start(array $o = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->employer)->post('/employer/bulk', $o + ['tokens' => $this->mixedList(), 'cgpa_min' => '3.50']);
    }

    private function run(VerificationBatch $b): array
    {
        $p = [];
        for ($i = 0; $i < 20 && ($p['status'] ?? '') !== 'done'; $i++) {
            $p = app(BatchVerifier::class)->step($b->refresh());
        }

        return $p;
    }

    public function test_mixed_batch_end_to_end(): void
    {
        $this->seedMixed();
        $r = $this->start();
        $b = VerificationBatch::firstOrFail();
        $r->assertRedirect(route('employer.bulk.show', $b->batch_id));
        $this->assertSame(7, $b->total);
        $this->assertSame('queued', $b->status);

        $p = $this->run($b);
        $this->assertSame('done', $p['status']);
        $this->assertSame(7, $p['processed']);
        $this->assertSame(['PASS' => 1, 'FAIL' => 1, 'NOT PROVEN' => 0, 'INVALID' => 4, 'DUPLICATE' => 1], $p['counts']);

        $by = VerificationBatchItem::where('batch_id', $b->batch_id)->get()->keyBy('row');
        $this->assertSame('PASS', $by[1]->overall);
        $this->assertSame('FAIL', $by[2]->overall);
        $this->assertSame('expired', $by[3]->reason);
        $this->assertSame('revoked', $by[4]->reason);
        $this->assertSame('unknown_token', $by[5]->reason);
        $this->assertSame('malformed_token', $by[6]->reason);
        $this->assertSame('duplicate_token', $by[7]->reason);
        $this->assertSame('Alice', $by[1]->candidate_name);

        // each real token went through the single-check path: usage log + audit with the batch id; malformed/duplicate rows did not
        $this->assertSame(5, TokenUsageLog::where('batch_id', $b->batch_id)->count());
        $this->assertSame(5, AuditLog::whereIn('action', ['token.verified', 'token.verify_failed'])->count());
        $this->assertSame(1, AuditLog::where('action', 'bulk.created')->count());
        $this->assertSame(1, AuditLog::where('action', 'bulk.completed')->count());
        $this->run($b); // finishing twice must not audit twice
        $this->assertSame(1, AuditLog::where('action', 'bulk.completed')->count());
    }

    public function test_progress_advances_in_chunks_and_endpoints_work(): void
    {
        $this->seedMixed();
        $this->start();
        $b = VerificationBatch::firstOrFail();
        $p = app(BatchVerifier::class)->step($b, 2);
        $this->assertSame('running', $p['status']);
        $this->assertSame(2, $p['processed']);

        $this->actingAs($this->employer)->getJson("/employer/bulk/{$b->batch_id}/progress")->assertOk()->assertJson(['processed' => 2, 'total' => 7]);
        $this->actingAs($this->employer)->postJson("/employer/bulk/{$b->batch_id}/step")->assertOk()->assertJsonPath('processed', 7); // default chunk of 10 finishes it
        $this->actingAs($this->employer)->get("/employer/bulk/{$b->batch_id}")->assertOk()->assertSee('Export CSV');
    }

    public function test_stale_claims_are_returned_to_the_queue(): void
    {
        $this->seedMixed();
        $this->start();
        $b = VerificationBatch::firstOrFail();
        VerificationBatchItem::where('batch_id', $b->batch_id)->where('row', 1)->update(['status' => 'processing', 'claimed_at' => now()->subMinutes(10)]);
        $this->assertSame('done', $this->run($b)['status']);
        $this->assertSame('PASS', VerificationBatchItem::where('batch_id', $b->batch_id)->where('row', 1)->first()->overall);
    }

    public function test_limits_and_validation(): void
    {
        config(['academiq.bulk_max_tokens' => 3]);
        $this->start()->assertSessionHasErrors('tokens'); // 7 > 3
        $this->actingAs($this->employer)->post('/employer/bulk', ['tokens' => self::T1])->assertSessionHasErrors('criteria');
        $this->actingAs($this->employer)->post('/employer/bulk', ['cgpa_min' => '3.50'])->assertSessionHasErrors('tokens');
        $this->actingAs($this->employer)->post('/employer/bulk', ['tokens' => self::T1, 'cgpa_min' => '3.10'])->assertSessionHasErrors('cgpa_min');
        $this->assertSame(0, VerificationBatch::count());
    }

    public function test_csv_file_input_with_optional_columns(): void
    {
        $this->seedMixed();
        $csv = UploadedFile::fake()->createWithContent('tokens.csv', "token_id,candidate_name,cv_ref\n".self::T1.",Alice,CV-9\n".self::T2.",Bob,\n");
        $this->actingAs($this->employer)->post('/employer/bulk', ['file' => $csv, 'cgpa_min' => '3.50'])->assertSessionHasNoErrors();
        $b = VerificationBatch::firstOrFail();
        $this->assertSame(2, $b->total);
        $this->assertSame('CV-9', VerificationBatchItem::where('batch_id', $b->batch_id)->where('row', 1)->first()->cv_ref);

        $bad = UploadedFile::fake()->createWithContent('bad.csv', "tokens\n".self::T1."\n");
        $this->actingAs($this->employer)->post('/employer/bulk', ['file' => $bad, 'cgpa_min' => '3.50'])->assertSessionHasErrors('file');
    }

    public function test_filters_sorting_and_exports(): void
    {
        $this->seedMixed();
        $this->start(['tokens' => self::T1.', =HYPERLINK("http://x"), CV-1'."\n".self::T2.', Bob']);
        $b = VerificationBatch::firstOrFail();
        $this->run($b);

        $passed = $this->actingAs($this->employer)->get("/employer/bulk/{$b->batch_id}?filter=passed")->assertOk()->getContent();
        $this->assertStringContainsString(self::T1, $passed);
        $this->assertStringNotContainsString(self::T2, $passed);
        $failed = $this->actingAs($this->employer)->get("/employer/bulk/{$b->batch_id}?filter=failed&sort=overall&dir=desc")->assertOk()->getContent();
        $this->assertStringContainsString(self::T2, $failed);
        $this->assertStringNotContainsString(self::T1, $failed);

        $csv = $this->actingAs($this->employer)->get("/employer/bulk/{$b->batch_id}/csv")->assertOk()->streamedContent();
        $this->assertStringContainsString('CGPA at least 3.50', $csv);
        $this->assertStringContainsString(self::T1, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);              // formula injection neutralised
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $pdf = $this->actingAs($this->employer)->get("/employer/bulk/{$b->batch_id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame(2, AuditLog::where('action', 'bulk.exported')->count());
    }

    public function test_tampered_proofs_are_flagged_per_token(): void
    {
        $this->token(self::T1);
        $this->mock(Groth16Verifier::class, fn ($m) => $m->shouldReceive('verify')->andReturn(false));
        $this->start(['tokens' => self::T1]);
        $b = VerificationBatch::firstOrFail();
        $this->run($b);
        $i = VerificationBatchItem::where('batch_id', $b->batch_id)->first();
        $this->assertSame(['INVALID', 'invalid_proof'], [$i->overall, $i->reason]);
    }

    public function test_revoked_credential_fails_in_bulk(): void
    {
        $this->token(self::T1);
        app(CredentialService::class)->revoke($this->admin, $this->cred->credential_id, 'Issued in error');
        $this->start(['tokens' => self::T1]);
        $this->run(VerificationBatch::firstOrFail());
        $this->assertSame('credential_revoked', VerificationBatchItem::first()->reason);
    }

    public function test_access_control(): void
    {
        $this->seedMixed();
        $this->start();
        $b = VerificationBatch::firstOrFail();
        $other = $this->makeUser('employer', ['email' => 'e2@example.test']);
        foreach (['', '/progress', '/csv', '/pdf'] as $suffix) {
            $this->actingAs($other)->get("/employer/bulk/{$b->batch_id}$suffix")->assertNotFound();
        }
        $this->actingAs($other)->postJson("/employer/bulk/{$b->batch_id}/step")->assertNotFound();
        $student = User::where('role', 'student')->first();
        $this->actingAs($student)->get('/employer/bulk')->assertForbidden();
        $this->actingAs($student)->post('/employer/bulk', ['tokens' => self::T1, 'cgpa_min' => '3.50'])->assertForbidden();
    }
}
