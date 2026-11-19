<?php

namespace App\Services\Verification;

use App\Models\BackgroundVerification;
use App\Models\ConsentRecord;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\Credentials\FieldEncoder;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verification Gateway: 3.1 receive, 3.2 identify, 3.3 request consent, 3.4 validate consent,
 * 3.5 route academic, 3.6 route background. 3.7 lives in RequestCollector.
 * Nothing academic or background proceeds before decide() passes the consent gate.
 */
class GatewayService
{
    public const CONSENT_WINDOW_DAYS = 14;   // time the applicant has to decide
    public const CONSENT_VALID_DAYS = 30;    // time an approval stays usable
    public const TASK_DUE_DAYS = 30;
    private const ROLE_FOR = ['police' => 'police_verifier', 'reference' => 'referee', 'employment' => 'previous_employer'];

    /** 3.1 + 3.2. $in is the validated employer form (see Employer\RequestController). */
    public function receive(User $employer, array $in): VerificationRequest
    {
        $profile = EmployerProfile::where('user_id', (string) $employer->getKey())->first();
        if (! $employer->is_active || ! $employer->hasRole('employer') || ! $profile) {
            throw ValidationException::withMessages(['student_reference' => 'Your employer account cannot submit requests.']);
        }
        $student = StudentReference::where('student_reference', $in['student_reference'])->where('account_status', 'active')->first();
        if (! $student) {
            throw ValidationException::withMessages(['student_reference' => 'No active student with that reference.']);
        }

        $academic = $this->academicClaim($in);
        $background = [];
        foreach (self::ROLE_FOR as $type => $role) {
            if (empty($in[$type])) {
                continue;
            }
            $participant = User::find($in[$type.'_participant'] ?? null);
            if (! $participant || ! $participant->is_active || $participant->role !== $role) {
                throw ValidationException::withMessages([$type.'_participant' => 'Choose a valid participant for this check.']);
            }
            $background[] = ['type' => $type, 'participant_id' => (string) $participant->getKey(), 'details' => $this->details($type, $in)];
        }
        if (! $academic && ! $background) {
            throw ValidationException::withMessages(['academic_kind' => 'Select at least one check.']);
        }

        $id = 'REQ-'.Str::upper(Str::random(10));
        $req = VerificationRequest::create([
            'request_id' => $id, 'employer_id' => (string) $employer->getKey(), 'company_name' => $profile->company_name,
            'student_reference' => $student->student_reference, 'student_user_id' => $student->user_id,
            'requested_checks' => ['academic' => $academic, 'background' => $background],
            'request_status' => 'awaiting_consent', 'academic_state' => null, 'academic_result' => null,
            'consent_deadline' => now()->addDays(self::CONSENT_WINDOW_DAYS),
        ]);
        AuditLogger::log('request.created', 'verification_request', $id, ['checks' => $this->scopeOf($req)], $employer);
        // 3.3: notify the applicant
        Notifier::send($student->user_id, 'consent_requested', 'Verification request from '.$profile->company_name, 'Review the requested checks and decide whether to consent.', route('student.requests.show', $id));

        return $req;
    }

    /** 3.4 consent gate, then 3.5/3.6 routing. $approved: ['academic'=>bool,'police'=>bool,...] */
    public function decide(User $student, string $requestId, array $approved): VerificationRequest
    {
        $ref = StudentProfile::where('user_id', (string) $student->getKey())->value('student_reference');
        $req = VerificationRequest::where('request_id', $requestId)->where('student_reference', $ref ?? '-')->first();
        if (! $req) {
            abort(404);
        }
        if ($req->request_status !== 'awaiting_consent' || $req->consent_deadline->isPast()) {
            throw ValidationException::withMessages(['consent' => 'This request is no longer awaiting your decision.']);
        }
        $requested = $this->scopeOf($req);
        $approvedScope = [];
        foreach ($requested as $item => $_) {
            $approvedScope[$item] = ! empty($approved[$item]);
        }
        $any = in_array(true, $approvedScope, true);

        return DB::connection('mongodb')->transaction(function () use ($req, $student, $requested, $approvedScope, $any) {
            // Compare-and-set: exactly one decision per request.
            $next = $any ? 'in_progress' : 'rejected';
            $won = VerificationRequest::where('request_id', $req->request_id)->where('request_status', 'awaiting_consent')->update(['request_status' => $next]);
            if ($won !== 1) {
                throw ValidationException::withMessages(['consent' => 'This request was already decided.']);
            }
            ConsentRecord::create([
                'request_id' => $req->request_id, 'student_id' => (string) $student->getKey(), 'requested_scope' => $requested, 'approved_scope' => $approvedScope,
                'decision' => $any ? 'approved' : 'rejected', 'decision_timestamp' => now(), 'authorization_reference' => 'AUTH-'.Str::upper(Str::random(10)),
                'expires_at' => now()->addDays(self::CONSENT_VALID_DAYS), 'revoked_at' => null,
            ]);
            AuditLogger::log($any ? 'consent.approved' : 'consent.rejected', 'verification_request', $req->request_id, ['approved_scope' => $approvedScope], $student);

            if ($any) {
                $this->route($req, $student, $approvedScope);
            }
            Notifier::send($req->employer_id, $any ? 'consent_approved' : 'consent_rejected', $any ? 'Applicant consented' : 'Applicant declined',
                'Request '.$req->request_id, route('employer.requests.show', $req->request_id));

            return VerificationRequest::where('request_id', $req->request_id)->first();
        });
    }

    /** 3.5 + 3.6, only for consented items. */
    private function route(VerificationRequest $req, User $student, array $scope): void
    {
        $checks = $req->requested_checks;
        if (! empty($scope['academic']) && ! empty($checks['academic'])) {
            VerificationRequest::where('request_id', $req->request_id)->update(['academic_state' => 'pending']);
            Notifier::send((string) $student->getKey(), 'proof_requested', 'Generate your proof', 'Your consent was recorded. Generate the proof in your wallet.', route('student.proof.show', $req->request_id));
        }
        foreach ($checks['background'] as $task) {
            if (empty($scope[$task['type']])) {
                continue;
            }
            BackgroundVerification::create([
                'request_id' => $req->request_id, 'verification_type' => $task['type'], 'participant_reference' => $task['participant_id'], 'status' => 'sent',
                'details' => $task['details'] + ['applicant_name' => $student->name, 'student_reference' => $req->student_reference, 'requesting_company' => $req->company_name],
                'due_at' => now()->addDays(self::TASK_DUE_DAYS), 'response_timestamp' => null, 'response_reference' => null,
            ]);
            AuditLogger::log('background.routed', 'verification_request', $req->request_id, ['type' => $task['type']], $student);
            Notifier::send($task['participant_id'], 'background_requested', 'New verification request', ClaimDescriber::backgroundLabel($task['type']).' for request '.$req->request_id, route('external.dashboard'));
        }
    }

    /** Student withdraws consent: outstanding work stops; completed outcomes are kept. */
    public function revoke(User $student, string $requestId): VerificationRequest
    {
        $ref = StudentProfile::where('user_id', (string) $student->getKey())->value('student_reference');
        $req = VerificationRequest::where('request_id', $requestId)->where('student_reference', $ref ?? '-')->firstOrFail();
        if ($req->request_status !== 'in_progress') {
            throw ValidationException::withMessages(['consent' => 'Consent can only be withdrawn while verification is in progress.']);
        }

        return DB::connection('mongodb')->transaction(function () use ($req, $student) {
            ConsentRecord::where('request_id', $req->request_id)->where('decision', 'approved')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            BackgroundVerification::where('request_id', $req->request_id)->where('status', 'sent')->update(['status' => 'cancelled']);
            if (in_array($req->academic_state, ['pending'], true)) {
                VerificationRequest::where('request_id', $req->request_id)->update(['academic_state' => 'cancelled']);
            }
            VerificationRequest::where('request_id', $req->request_id)->update(['request_status' => 'withdrawn']);
            AuditLogger::log('consent.revoked', 'verification_request', $req->request_id, [], $student);
            Notifier::send($req->employer_id, 'consent_revoked', 'Applicant withdrew consent', 'Request '.$req->request_id, route('employer.requests.show', $req->request_id));

            return VerificationRequest::where('request_id', $req->request_id)->first();
        });
    }

    /** ['academic'=>true, 'police'=>true, ...] for everything requested. */
    public function scopeOf(VerificationRequest $req): array
    {
        $s = [];
        if (! empty($req->requested_checks['academic'])) {
            $s['academic'] = true;
        }
        foreach ($req->requested_checks['background'] as $t) {
            $s[$t['type']] = true;
        }

        return $s;
    }

    private function academicClaim(array $in): ?array
    {
        return match ($in['academic_kind'] ?? '') {
            'cgpa' => ['claim_type' => 1, 'param' => FieldEncoder::cgpaX100($in['cgpa_min'])],
            'programme' => ['claim_type' => 2, 'param' => (int) $in['programme_code']],
            'degree' => ['claim_type' => 3, 'param' => (int) $in['degree_category']],
            'issuer' => ['claim_type' => 4, 'param' => 0],
            default => null,
        };
    }

    private function details(string $type, array $in): array
    {
        return match ($type) {
            'police' => ['lawful_basis' => $in['police_basis']],
            'reference' => ['position_applied' => $in['reference_position']],
            'employment' => ['company' => $in['employment_company'], 'job_title' => $in['employment_title'], 'period' => $in['employment_period']],
        };
    }
}
