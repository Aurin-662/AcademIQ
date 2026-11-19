<?php

namespace App\Services\Verification;

use App\Events\VerificationCompleted;
use App\Models\BackgroundVerification;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\Notifier;

/** 3.7: collect academic + background outcomes and complete the request when nothing is outstanding. */
class RequestCollector
{
    private const OPEN_TASK = ['sent', 'pending'];

    public function refresh(string $requestId): void
    {
        $req = VerificationRequest::where('request_id', $requestId)->first();
        if (! $req || $req->request_status !== 'in_progress') {
            return;
        }
        $academicOpen = in_array($req->academic_state, ['pending', 'verifying'], true);
        $tasksOpen = BackgroundVerification::where('request_id', $requestId)->whereIn('status', self::OPEN_TASK)->exists();
        if ($academicOpen || $tasksOpen) {
            return;
        }
        // Atomic: only one caller completes the request, so the completion event fires once.
        if (VerificationRequest::where('request_id', $requestId)->where('request_status', 'in_progress')->update(['request_status' => 'completed', 'completed_at' => now()]) !== 1) {
            return;
        }
        AuditLogger::log('request.completed', 'verification_request', $requestId);
        Notifier::send($req->employer_id, 'verification_completed', 'Verification completed', 'Request '.$requestId, route('employer.requests.show', $requestId));
        Notifier::send($req->student_user_id, 'verification_completed', 'Verification completed', 'Request '.$requestId, route('student.requests.show', $requestId));
        event(new VerificationCompleted($requestId));
    }

    /** Employer-safe view: statuses and permitted outcomes only. No remarks, proofs or credential data. */
    public function summary(VerificationRequest $req): array
    {
        $scope = app(GatewayService::class)->scopeOf($req);
        $consent = \App\Models\ConsentRecord::where('request_id', $req->request_id)->first();
        $rows = [];
        if (isset($scope['academic'])) {
            $declined = $consent && ! ($consent->approved_scope['academic'] ?? false);
            $rows[] = ['label' => ClaimDescriber::academic($req->requested_checks['academic']), 'status' => $this->academicStatus($req, $consent, $declined)];
        }
        foreach ($req->requested_checks['background'] as $t) {
            $task = BackgroundVerification::where('request_id', $req->request_id)->where('verification_type', $t['type'])->first();
            $declined = $consent && ! ($consent->approved_scope[$t['type']] ?? false);
            $rows[] = ['label' => ClaimDescriber::backgroundLabel($t['type']), 'status' => $task ? ($task->status === 'responded' ? 'responded: '.str_replace('_', ' ', $task->outcome) : $task->status) : ($declined ? 'declined' : ($req->request_status === 'awaiting_consent' ? 'awaiting consent' : $req->request_status))];
        }

        return $rows;
    }

    private function academicStatus(VerificationRequest $req, $consent, bool $declined): string
    {
        if ($req->academic_state === 'done') {
            return $req->academic_result === 'TRUE' ? 'verified' : 'not verified';
        }
        if ($declined) {
            return 'declined';
        }
        if ($req->request_status === 'awaiting_consent') {
            return 'awaiting consent';
        }

        return match ($req->academic_state) {
            'pending' => 'awaiting applicant proof', 'verifying' => 'verifying', 'cancelled' => 'cancelled', default => $req->request_status,
        };
    }
}
