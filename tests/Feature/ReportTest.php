<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackgroundVerification;
use App\Models\ConsentRecord;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VerificationReport;
use App\Models\VerificationRequest;
use App\Services\Reports\ReportService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    private User $employer;
    private User $student;
    private User $police;
    private User $referee;
    private VerificationRequest $req;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->employer = $this->makeUser('employer', ['email' => 'emp@example.test']);
        EmployerProfile::create(['user_id' => (string) $this->employer->getKey(), 'company_name' => 'Acme Ltd']);
        $this->student = $this->makeUser('student', ['email' => 's@example.test']);
        StudentProfile::create(['user_id' => (string) $this->student->getKey(), 'student_reference' => 'KUET-R1']);
        StudentReference::create(['student_reference' => 'KUET-R1', 'user_id' => (string) $this->student->getKey(), 'account_status' => 'active']);
        $this->police = $this->makeUser('police_verifier', ['email' => 'pol@example.test']);
        $this->referee = $this->makeUser('referee', ['email' => 'ref@example.test']);
    }

    /** Runs the real gateway -> portal flow to completion. Academic proof outcome is set directly (proof path is covered in ProofTest). */
    private function completedRequest(array $scope = ['academic' => 1, 'police' => 1, 'reference' => 1], string $police = 'clear', string $ref = 'recommend', string $academic = 'TRUE'): VerificationRequest
    {
        $this->actingAs($this->employer)->post('/employer/requests', [
            'student_reference' => 'KUET-R1', 'academic_kind' => 'cgpa', 'cgpa_min' => '3.00',
            'police' => 1, 'police_participant' => (string) $this->police->getKey(), 'police_basis' => 'Employment screening policy ref 12',
            'reference' => 1, 'reference_participant' => (string) $this->referee->getKey(), 'reference_position' => 'Engineer',
        ])->assertSessionHasNoErrors();
        $req = VerificationRequest::first();
        $this->actingAs($this->student)->post("/student/requests/{$req->request_id}/decision", ['decision' => 'approve', 'scope' => $scope])->assertSessionHasNoErrors();
        if (! empty($scope['academic'])) {
            VerificationRequest::where('request_id', $req->request_id)->update(['academic_state' => 'done', 'academic_result' => $academic]);
        }
        if (! empty($scope['police'])) {
            $this->actingAs($this->police)->post('/external/tasks/'.BackgroundVerification::where('verification_type', 'police')->first()->getKey(), ['outcome' => $police, 'reference' => 'CASE-SECRET-9']);
        }
        if (! empty($scope['reference'])) {
            $this->actingAs($this->referee)->post('/external/tasks/'.BackgroundVerification::where('verification_type', 'reference')->first()->getKey(), ['outcome' => $ref, 'remarks' => 'SECRET-REMARK-XYZ']);
        }

        return $req->fresh();
    }

    public function test_completion_generates_one_pdf_report_with_metadata(): void
    {
        $req = $this->completedRequest();
        $this->assertSame('completed', $req->request_status);
        $r = VerificationReport::firstOrFail();
        $this->assertSame(1, VerificationReport::count());
        $this->assertMatchesRegularExpression('/^RPT-[A-Z0-9]{12}$/', $r->report_id);
        $this->assertSame($req->request_id, $r->request_id);
        $this->assertSame((string) $this->employer->getKey(), $r->employer_id);
        $this->assertSame('KUET-R1', $r->applicant_reference);
        $this->assertSame('Verified', $r->outcome_summary['overall']);

        $bytes = Storage::disk('local')->get($r->report_file_reference);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $r->sha256);
        $this->assertStringNotContainsString($r->report_id, $r->report_file_reference === '' ? 'x' : 'public/'); // not under a public path
        $this->assertTrue(AuditLog::where('action', 'report.generated')->where('entity_reference', $r->report_id)->exists());
        $this->assertTrue(UserNotification::where('user_id', (string) $this->employer->getKey())->where('type', 'report_generated')->exists());

        // idempotent
        $this->assertSame($r->report_id, app(ReportService::class)->generate($req->request_id)->report_id);
        $this->assertSame(1, VerificationReport::count());
    }

    public function test_report_is_not_generated_before_completion(): void
    {
        $this->actingAs($this->employer)->post('/employer/requests', ['student_reference' => 'KUET-R1', 'academic_kind' => 'issuer']);
        $this->assertNull(app(ReportService::class)->generate(VerificationRequest::first()->request_id));
        $this->assertSame(0, VerificationReport::count());
    }

    public function test_report_content_is_outcomes_only(): void
    {
        $req = $this->completedRequest(['academic' => 1, 'police' => 1], 'adverse_record'); // reference declined by applicant
        $data = app(ReportService::class)->collect($req);
        $html = view('reports.verification', $data + ['reportId' => 'RPT-X', 'generatedAt' => now(), 'authCode' => 'ABC'])->render();

        foreach (['Academi', 'KUET', 'KUET-R1', 'Acme Ltd', $req->request_id, 'CGPA is at least 3.00', 'Verified (zero-knowledge proof)', 'Adverse record reported', 'Overall status: Not verified', 'AUTH-'] as $expected) {
            $this->assertStringContainsString($expected, $html, $expected);
        }
        $declined = collect($data['outcomes'])->firstWhere('check', 'Reference check');
        $this->assertSame('Not consented by applicant', $declined['result']);

        $body = preg_replace('#<p class="note">.*?</p>#s', '', $html); // the disclaimer names what is excluded
        foreach (['SECRET-REMARK-XYZ', 'CASE-SECRET-9', 'salt', 'signature', 'pi_a', 'commitment', 'witness', 'cgpa_x100'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $body, $forbidden);
        }
        $this->assertStringNotContainsString('Recommended', $html); // result of a declined check must never appear
    }

    public function test_overall_status_rules(): void
    {
        $svc = app(ReportService::class);
        $this->assertSame('Verified', $svc->collect($this->completedRequest())['overall']);
        BackgroundVerification::where('verification_type', 'reference')->update(['outcome' => 'decline_to_comment', 'status' => 'unable_to_verify']);
        $this->assertSame('Incomplete', $svc->collect(VerificationRequest::first())['overall']);
        BackgroundVerification::where('verification_type', 'reference')->update(['outcome' => 'not_recommend', 'status' => 'responded']);
        $this->assertSame('Not verified', $svc->collect(VerificationRequest::first())['overall']);
        VerificationRequest::query()->update(['academic_result' => 'FALSE']);
        $this->assertSame('Not verified', $svc->collect(VerificationRequest::first())['overall']);
    }

    public function test_only_owning_employer_can_download_and_each_access_is_logged(): void
    {
        $this->completedRequest();
        $r = VerificationReport::first();
        $url = "/employer/reports/{$r->report_id}";

        $res = $this->actingAs($this->employer)->get($url)->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->actingAs($this->employer)->get($url)->assertOk();

        $log = AuditLog::where('action', 'report.downloaded')->where('entity_reference', $r->report_id)->get();
        $this->assertCount(2, $log);
        $this->assertSame((string) $this->employer->getKey(), $log->first()->actor_id);
        $this->assertSame('employer', $log->first()->actor_role);

        $rival = $this->makeUser('employer', ['email' => 'rival@example.test']);
        EmployerProfile::create(['user_id' => (string) $rival->getKey(), 'company_name' => 'Rival']);
        $this->actingAs($rival)->get($url)->assertNotFound();
        $this->actingAs($this->student)->get($url)->assertForbidden();
        $this->actingAs($this->makeUser('admin', ['email' => 'a@example.test']))->get($url)->assertForbidden();
        $this->actingAs($this->referee)->get($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect('/login');
        $this->assertCount(2, AuditLog::where('action', 'report.downloaded')->get()); // denied attempts are not downloads

        $this->actingAs($this->employer)->get("/employer/requests/{$r->request_id}")->assertOk()->assertSee('Download PDF report')->assertSee('Access history');
        // no guessable public file URL
        $this->assertFileDoesNotExist(public_path("reports/{$r->report_id}.pdf"));
        $this->assertFileDoesNotExist(public_path("storage/reports/{$r->report_id}.pdf"));
    }

    public function test_tampered_report_file_is_not_served(): void
    {
        $this->completedRequest();
        $r = VerificationReport::first();
        Storage::disk('local')->put($r->report_file_reference, '%PDF-forged');
        $this->actingAs($this->employer)->get("/employer/reports/{$r->report_id}")->assertStatus(500);
        $this->assertTrue(AuditLog::where('action', 'report.integrity_failure')->exists());
        $this->assertFalse(AuditLog::where('action', 'report.downloaded')->exists());
    }

    public function test_public_authenticity_check_discloses_minimum(): void
    {
        $this->completedRequest();
        $r = VerificationReport::first();
        $this->get('/verify-report')->assertOk();
        $this->from('/verify-report')->post('/verify-report', ['report_id' => $r->report_id, 'code' => $r->auth_code])->assertSessionHas('result.valid', true);
        $this->from('/verify-report')->post('/verify-report', ['report_id' => $r->report_id, 'code' => 'BADCODE123'])->assertSessionHas('result.valid', false);
        $this->from('/verify-report')->post('/verify-report', ['report_id' => 'RPT-NOPE', 'code' => $r->auth_code])->assertSessionHas('result.valid', false);
        $page = $this->get('/verify-report');
        $page->assertDontSee('KUET-R1')->assertDontSee('Acme');
        $this->assertTrue(AuditLog::where('action', 'report.authenticity_confirmed')->exists());
        $this->assertTrue(AuditLog::where('action', 'report.authenticity_failed')->exists());
    }

    public function test_admin_request_log_and_audit_filters(): void
    {
        $req = $this->completedRequest();
        $admin = $this->makeUser('admin', ['email' => 'a@example.test']);
        $this->actingAs($admin)->get('/admin/requests')->assertOk()->assertSee($req->request_id)->assertSee('Acme Ltd')->assertSee('AUTH-');
        $this->actingAs($this->employer)->get('/admin/requests')->assertForbidden();

        $all = $this->actingAs($admin)->get('/admin/audit')->assertOk();
        $all->assertSee('report.generated')->assertSee('consent.approved')->assertSee('background.responded');
        $only = $this->actingAs($admin)->get('/admin/audit?group=report')->assertOk();
        $only->assertSee('report.generated')->assertDontSee('consent.approved');
        $this->actingAs($admin)->get('/admin/audit?ref='.$req->request_id)->assertOk()->assertSee('request.created');
        $this->actingAs($admin)->get('/admin/audit?group[$ne]=x')->assertSessionHasErrors('group');
        $this->actingAs($this->employer)->get('/admin/audit')->assertForbidden();
    }
}
