<?php

namespace Tests\Feature;

use App\Events\VerificationCompleted;
use App\Models\AuditLog;
use App\Models\BackgroundVerification;
use App\Models\EmployerProfile;
use App\Models\EmploymentVerification;
use App\Models\PoliceVerification;
use App\Models\RefereeResponse;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BackgroundTest extends TestCase
{
    private User $employer;
    private User $student;
    private User $police;
    private User $referee;
    private User $prev;
    private VerificationRequest $req;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employer = $this->makeUser('employer', ['email' => 'emp@example.test']);
        EmployerProfile::create(['user_id' => (string) $this->employer->getKey(), 'company_name' => 'Acme Ltd']);
        $this->student = $this->makeUser('student', ['email' => 's@example.test']);
        StudentProfile::create(['user_id' => (string) $this->student->getKey(), 'student_reference' => 'KUET-B1']);
        StudentReference::create(['student_reference' => 'KUET-B1', 'user_id' => (string) $this->student->getKey(), 'account_status' => 'active']);
        $this->police = $this->makeUser('police_verifier', ['email' => 'pol@example.test']);
        $this->referee = $this->makeUser('referee', ['email' => 'ref@example.test']);
        $this->prev = $this->makeUser('previous_employer', ['email' => 'prev@example.test']);

        $this->actingAs($this->employer)->post('/employer/requests', [
            'student_reference' => 'KUET-B1', 'police' => 1, 'police_participant' => (string) $this->police->getKey(), 'police_basis' => 'Employment screening policy ref 12',
            'reference' => 1, 'reference_participant' => (string) $this->referee->getKey(), 'reference_position' => 'Engineer',
            'employment' => 1, 'employment_participant' => (string) $this->prev->getKey(), 'employment_company' => 'OldCo', 'employment_title' => 'Intern', 'employment_period' => '2022-2023',
        ])->assertSessionHasNoErrors();
        $this->req = VerificationRequest::first();
    }

    private function consent(array $scope = ['police' => 1, 'reference' => 1, 'employment' => 1]): void
    {
        $this->actingAs($this->student)->post("/student/requests/{$this->req->request_id}/decision", ['decision' => 'approve', 'scope' => $scope])->assertSessionHasNoErrors();
    }

    private function task(string $type): BackgroundVerification
    {
        return BackgroundVerification::where('verification_type', $type)->firstOrFail();
    }

    public function test_participants_see_only_their_own_tasks(): void
    {
        $this->actingAs($this->police)->get('/external')->assertOk()->assertDontSee('Student'); // no tasks before consent
        $this->consent();

        $this->actingAs($this->police)->get('/external')->assertOk()->assertSee('Police verification')->assertDontSee('Reference check')->assertDontSee('Work experience');
        $this->actingAs($this->referee)->get('/external')->assertSee('Reference check')->assertDontSee('Police verification');

        $polTask = (string) $this->task('police')->getKey();
        $this->actingAs($this->police)->get("/external/tasks/$polTask")->assertOk()->assertSee('Employment screening policy ref 12');
        $this->actingAs($this->referee)->get("/external/tasks/$polTask")->assertNotFound();
        $this->actingAs($this->prev)->get("/external/tasks/$polTask")->assertNotFound();
        $other = $this->makeUser('police_verifier', ['email' => 'pol2@example.test']);
        $this->actingAs($other)->get("/external/tasks/$polTask")->assertNotFound();
        $this->actingAs($other)->post("/external/tasks/$polTask", ['outcome' => 'clear'])->assertNotFound();
        $this->assertSame('sent', $this->task('police')->status);
    }

    public function test_other_roles_cannot_use_portal(): void
    {
        foreach ([$this->employer, $this->student, $this->makeUser('admin', ['email' => 'a@example.test'])] as $u) {
            $this->actingAs($u)->get('/external')->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->get('/external')->assertRedirect('/login');
    }

    public function test_each_participant_response_is_recorded_in_its_own_collection(): void
    {
        Event::fake([VerificationCompleted::class]);
        $this->consent();

        $this->actingAs($this->police)->post('/external/tasks/'.$this->task('police')->getKey(), ['outcome' => 'clear', 'reference' => 'PCC-77'])->assertSessionHasNoErrors();
        $this->actingAs($this->referee)->post('/external/tasks/'.$this->task('reference')->getKey(), ['outcome' => 'recommend', 'remarks' => 'Private remark about the candidate'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $this->req->fresh()->request_status); // employment still open
        $this->actingAs($this->prev)->post('/external/tasks/'.$this->task('employment')->getKey(), ['outcome' => 'confirmed'])->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $this->task('police')->status);
        $this->assertSame('responded', $this->task('reference')->status);
        $this->assertSame('confirmed', $this->task('employment')->status);
        $this->assertNotNull($this->task('police')->response_timestamp);
        $this->assertSame('PCC-77', $this->task('police')->response_reference);
        $this->assertSame('clear', PoliceVerification::first()->result_status);
        $this->assertSame((string) $this->police->getKey(), PoliceVerification::first()->authorized_participant_reference);
        $this->assertSame('recommend', RefereeResponse::first()->response);
        $this->assertSame('confirmed', EmploymentVerification::first()->employment_status);

        $this->assertSame('completed', $this->req->fresh()->request_status);
        Event::assertDispatchedTimes(VerificationCompleted::class, 1);
        $this->assertSame(3, AuditLog::where('action', 'background.responded')->count());
        $this->assertTrue(UserNotification::where('user_id', (string) $this->employer->getKey())->where('type', 'background_responded')->exists());
    }

    public function test_employer_sees_outcomes_but_never_remarks(): void
    {
        $this->consent();
        $this->actingAs($this->referee)->post('/external/tasks/'.$this->task('reference')->getKey(), ['outcome' => 'recommend_with_reservations', 'remarks' => 'SECRET-REMARK-XYZ']);
        $this->actingAs($this->police)->post('/external/tasks/'.$this->task('police')->getKey(), ['outcome' => 'adverse_record', 'reference' => 'CASE-SECRET-1']);

        $page = $this->actingAs($this->employer)->get("/employer/requests/{$this->req->request_id}")->assertOk();
        $page->assertSee('Responded: recommend with reservations')->assertSee('Rejected');
        $page->assertDontSee('SECRET-REMARK-XYZ')->assertDontSee('CASE-SECRET-1')->assertDontSee('adverse');
    }

    public function test_response_is_single_use_and_validated(): void
    {
        $this->consent();
        $url = '/external/tasks/'.$this->task('police')->getKey();
        $this->actingAs($this->police)->post($url, ['outcome' => 'recommend'])->assertSessionHasErrors('outcome'); // outcome from another check type
        $this->actingAs($this->police)->post($url, [])->assertSessionHasErrors('outcome');
        $this->assertSame('sent', $this->task('police')->status);

        $this->actingAs($this->police)->post($url, ['outcome' => 'clear'])->assertSessionHasNoErrors();
        $this->actingAs($this->police)->post($url, ['outcome' => 'adverse_record'])->assertSessionHasErrors('task');
        $this->assertSame('confirmed', $this->task('police')->status);
        $this->assertSame(1, PoliceVerification::count());
    }

    public function test_no_response_after_consent_withdrawn_or_task_expired(): void
    {
        $this->consent();
        $pol = '/external/tasks/'.$this->task('police')->getKey();
        $ref = '/external/tasks/'.$this->task('reference')->getKey();
        BackgroundVerification::where('verification_type', 'reference')->update(['due_at' => now()->subDay()]);
        $this->actingAs($this->referee)->post($ref, ['outcome' => 'recommend'])->assertSessionHasErrors('task');

        $this->actingAs($this->student)->post("/student/requests/{$this->req->request_id}/revoke");
        $this->actingAs($this->police)->post($pol, ['outcome' => 'clear'])->assertSessionHasErrors('task');
        $this->assertSame(0, PoliceVerification::count());
        $this->assertSame('cancelled', $this->task('police')->status);
    }

    public function test_declined_checks_never_reach_participants(): void
    {
        $this->consent(['police' => 1]);
        $this->actingAs($this->referee)->get('/external')->assertDontSee('Reference check');
        $this->assertSame(1, BackgroundVerification::count());
    }

    public function test_expiry_command_expires_open_tasks_and_completes_request(): void
    {
        $this->consent(['police' => 1]);
        BackgroundVerification::query()->update(['due_at' => now()->subDay()]);
        $this->artisan('academiq:expire-requests')->assertSuccessful();
        $this->assertSame('expired', $this->task('police')->status);
        $this->assertSame('completed', $this->req->fresh()->request_status);
        $this->assertSame('expired', collect(app(\App\Services\Verification\RequestCollector::class)->summary($this->req->fresh()))->firstWhere('label', 'Police verification')['status']);
    }
}
