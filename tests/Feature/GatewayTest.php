<?php

namespace Tests\Feature;

use App\Events\VerificationCompleted;
use App\Models\AuditLog;
use App\Models\BackgroundVerification;
use App\Models\ConsentRecord;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VerificationRequest;
use App\Services\Verification\RequestCollector;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    private User $employer;
    private User $student;
    private User $police;
    private User $referee;
    private User $prev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employer = $this->makeUser('employer', ['email' => 'emp@example.test']);
        EmployerProfile::create(['user_id' => (string) $this->employer->getKey(), 'company_name' => 'Acme Ltd']);
        $this->student = $this->makeUser('student', ['email' => 's@example.test']);
        StudentProfile::create(['user_id' => (string) $this->student->getKey(), 'student_reference' => 'KUET-G1']);
        StudentReference::create(['student_reference' => 'KUET-G1', 'user_id' => (string) $this->student->getKey(), 'account_status' => 'active']);
        $this->police = $this->makeUser('police_verifier', ['email' => 'pol@example.test']);
        $this->referee = $this->makeUser('referee', ['email' => 'ref@example.test']);
        $this->prev = $this->makeUser('previous_employer', ['email' => 'prev@example.test']);
    }

    private function form(array $o = []): array
    {
        return $o + ['student_reference' => 'KUET-G1', 'academic_kind' => 'cgpa', 'cgpa_min' => '3.00',
            'police' => 1, 'police_participant' => (string) $this->police->getKey(), 'police_basis' => 'Employment screening policy ref 12',
            'reference' => 1, 'reference_participant' => (string) $this->referee->getKey(), 'reference_position' => 'Engineer',
            'employment' => 1, 'employment_participant' => (string) $this->prev->getKey(), 'employment_company' => 'OldCo', 'employment_title' => 'Intern', 'employment_period' => '2022-2023'];
    }

    private function create(array $o = []): VerificationRequest
    {
        $this->actingAs($this->employer)->post('/employer/requests', $this->form($o))->assertSessionHasNoErrors();

        return VerificationRequest::orderByDesc('created_at')->first();
    }

    public function test_employer_creates_request_and_applicant_is_notified(): void
    {
        $r = $this->create();
        $this->assertSame('awaiting_consent', $r->request_status);
        $this->assertMatchesRegularExpression('/^REQ-[A-Z0-9]{10}$/', $r->request_id);
        $this->assertSame(['claim_type' => 1, 'param' => 300], $r->requested_checks['academic']);
        $this->assertCount(3, $r->requested_checks['background']);
        $this->assertSame(0, BackgroundVerification::count()); // nothing routed before consent
        $this->assertNull($r->academic_state);
        $this->assertTrue(UserNotification::where('user_id', (string) $this->student->getKey())->where('type', 'consent_requested')->exists());
        $this->assertTrue(AuditLog::where('action', 'request.created')->exists());
        $this->actingAs($this->employer)->get('/employer')->assertOk()->assertSee($r->request_id);
    }

    public function test_request_validation_and_employer_gate(): void
    {
        $e = $this->actingAs($this->employer);
        $e->post('/employer/requests', $this->form(['student_reference' => 'NOPE-1']))->assertSessionHasErrors('student_reference');
        $e->post('/employer/requests', ['student_reference' => 'KUET-G1'])->assertSessionHasErrors('academic_kind');
        $e->post('/employer/requests', $this->form(['police_basis' => 'x']))->assertSessionHasErrors('police_basis'); // lawful basis required
        $e->post('/employer/requests', $this->form(['police_participant' => (string) $this->referee->getKey()]))->assertSessionHasErrors('police_participant'); // wrong role
        $e->post('/employer/requests', $this->form(['cgpa_min' => '4.5']))->assertSessionHasErrors('cgpa_min');
        $this->assertSame(0, VerificationRequest::count());
        $this->actingAs($this->student)->post('/employer/requests', $this->form())->assertForbidden();
        $this->employer->is_active = false;
        $this->employer->save();
        $this->actingAs($this->employer)->post('/employer/requests', $this->form())->assertForbidden();
    }

    public function test_consent_approval_routes_only_consented_checks(): void
    {
        $r = $this->create();
        $this->actingAs($this->student)->get("/student/requests/{$r->request_id}")->assertOk()->assertSee('CGPA is at least 3.00')->assertSee('Acme Ltd')->assertSee('Police verification');
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['academic' => 1, 'police' => 1, 'employment' => 1]])->assertSessionHasNoErrors();

        $r = $r->fresh();
        $this->assertSame('in_progress', $r->request_status);
        $this->assertSame('pending', $r->academic_state);
        $c = ConsentRecord::first();
        $this->assertSame('approved', $c->decision);
        $this->assertSame(['academic' => true, 'police' => true, 'reference' => false, 'employment' => true], $c->approved_scope);
        $this->assertNotEmpty($c->authorization_reference);
        $types = BackgroundVerification::pluck('verification_type')->sort()->values()->all();
        $this->assertSame(['employment', 'police'], $types); // reference was declined, so never routed
        $t = BackgroundVerification::where('verification_type', 'police')->first();
        $this->assertSame((string) $this->police->getKey(), $t->participant_reference);
        $this->assertSame('sent', $t->status);
        $this->assertSame('Student', $t->details['applicant_name']);
        $this->assertTrue(AuditLog::where('action', 'consent.approved')->exists());
        $this->assertTrue(UserNotification::where('user_id', (string) $this->police->getKey())->exists());
        $this->assertFalse(UserNotification::where('user_id', (string) $this->referee->getKey())->exists());

        $summary = collect(app(RequestCollector::class)->summary($r))->pluck('status', 'label');
        $this->assertSame('declined', $summary['Reference check']);
        $this->assertSame('awaiting applicant proof', $summary['CGPA is at least 3.00']);
    }

    public function test_rejection_routes_nothing(): void
    {
        $r = $this->create();
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $r->fresh()->request_status);
        $this->assertSame('rejected', ConsentRecord::first()->decision);
        $this->assertSame(0, BackgroundVerification::count());
        $this->assertNull($r->fresh()->academic_state);
        // approving with nothing selected is also a rejection
        $r2 = $this->create();
        $this->actingAs($this->student)->post("/student/requests/{$r2->request_id}/decision", ['decision' => 'approve', 'scope' => []]);
        $this->assertSame('rejected', $r2->fresh()->request_status);
        $this->assertTrue(AuditLog::where('action', 'consent.rejected')->exists());
    }

    public function test_only_the_applicant_can_decide_and_only_once(): void
    {
        $r = $this->create();
        $other = $this->makeUser('student', ['email' => 'o@example.test']);
        StudentProfile::create(['user_id' => (string) $other->getKey(), 'student_reference' => 'KUET-G2']);
        $this->actingAs($other)->get("/student/requests/{$r->request_id}")->assertNotFound();
        $this->actingAs($other)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['academic' => 1]])->assertNotFound();
        $this->actingAs($this->employer)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('awaiting_consent', $r->fresh()->request_status);

        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['academic' => 1]]);
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['police' => 1]])->assertSessionHasErrors('consent');
        $this->assertSame(1, ConsentRecord::count());
        $this->assertSame(0, BackgroundVerification::count());
    }

    public function test_expired_request_cannot_be_decided_and_expiry_command(): void
    {
        $r = $this->create();
        VerificationRequest::where('request_id', $r->request_id)->update(['consent_deadline' => now()->subDay()]);
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['academic' => 1]])->assertSessionHasErrors('consent');
        $this->assertSame(0, ConsentRecord::count());
        $this->artisan('academiq:expire-requests')->assertSuccessful();
        $this->assertSame('expired', $r->fresh()->request_status);
    }

    public function test_unconsented_request_has_no_proof_path(): void
    {
        $r = $this->create();
        $this->actingAs($this->student)->getJson("/student/verify/{$r->request_id}/instruction")->assertNotFound();
    }

    public function test_employer_cannot_see_other_employers_requests(): void
    {
        $r = $this->create();
        $e2 = $this->makeUser('employer', ['email' => 'e2@example.test']);
        EmployerProfile::create(['user_id' => (string) $e2->getKey(), 'company_name' => 'Rival']);
        $this->actingAs($e2)->get("/employer/requests/{$r->request_id}")->assertNotFound();
        $this->actingAs($e2)->get('/employer')->assertOk()->assertDontSee($r->request_id);
        $this->actingAs($this->student)->get("/employer/requests/{$r->request_id}")->assertForbidden();
    }

    public function test_revoking_consent_cancels_outstanding_work(): void
    {
        $r = $this->create();
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['academic' => 1, 'police' => 1]]);
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/revoke")->assertSessionHasNoErrors();
        $r = $r->fresh();
        $this->assertSame('withdrawn', $r->request_status);
        $this->assertSame('cancelled', $r->academic_state);
        $this->assertSame('cancelled', BackgroundVerification::first()->status);
        $this->assertNotNull(ConsentRecord::first()->revoked_at);
        $this->actingAs($this->student)->getJson("/student/verify/{$r->request_id}/instruction")->assertNotFound();
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/revoke")->assertSessionHasErrors('consent');
    }

    public function test_collector_completes_once_when_everything_is_terminal(): void
    {
        Event::fake([VerificationCompleted::class]);
        $r = $this->create();
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['academic' => 1, 'police' => 1]]);
        $collector = app(RequestCollector::class);

        $collector->refresh($r->request_id);
        $this->assertSame('in_progress', $r->fresh()->request_status); // academic + police still open

        VerificationRequest::where('request_id', $r->request_id)->update(['academic_state' => 'done', 'academic_result' => 'TRUE']);
        $collector->refresh($r->request_id);
        $this->assertSame('in_progress', $r->fresh()->request_status); // police still open

        BackgroundVerification::where('verification_type', 'police')->update(['status' => 'confirmed']);
        $collector->refresh($r->request_id);
        $collector->refresh($r->request_id);
        $this->assertSame('completed', $r->fresh()->request_status);
        Event::assertDispatchedTimes(VerificationCompleted::class, 1);
        $this->assertTrue(UserNotification::where('user_id', (string) $this->employer->getKey())->where('type', 'verification_completed')->exists());
    }

    public function test_background_only_request_has_no_academic_gate(): void
    {
        $r = $this->create(['academic_kind' => '', 'reference' => 0, 'employment' => 0]);
        $this->actingAs($this->student)->post("/student/requests/{$r->request_id}/decision", ['decision' => 'approve', 'scope' => ['police' => 1]]);
        $this->assertNull($r->fresh()->academic_state);
        BackgroundVerification::query()->update(['status' => 'rejected']);
        app(RequestCollector::class)->refresh($r->request_id);
        $this->assertSame('completed', $r->fresh()->request_status);
    }

    public function test_student_dashboard_lists_pending_requests(): void
    {
        $r = $this->create();
        $this->actingAs($this->student)->get('/student')->assertOk()->assertSee('Acme Ltd is asking for your consent');
        $this->actingAs($this->student)->get('/student/requests')->assertOk()->assertSee($r->request_id);
    }
}
