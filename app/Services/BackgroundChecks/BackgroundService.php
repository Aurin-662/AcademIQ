<?php

namespace App\Services\BackgroundChecks;

use App\Models\BackgroundVerification;
use App\Models\EmploymentVerification;
use App\Models\PoliceVerification;
use App\Models\RefereeResponse;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Services\Verification\RequestCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Processes 4.1 police, 4.2 reference, 4.3 work experience.
 * A response is recorded only from the assigned participant, once, while the request is still in progress.
 * Nothing here is an official integration: responses are typed in manually by the participant.
 */
class BackgroundService
{
    /** role => verification type */
    public const TYPE_FOR_ROLE = ['police_verifier' => 'police', 'referee' => 'reference', 'previous_employer' => 'employment'];

    /** outcome => resulting task status, per type */
    public const OUTCOMES = [
        'police' => ['clear' => 'confirmed', 'adverse_record' => 'rejected', 'unable_to_verify' => 'unable_to_verify'],
        'reference' => ['recommend' => 'responded', 'recommend_with_reservations' => 'responded', 'not_recommend' => 'responded', 'decline_to_comment' => 'unable_to_verify'],
        'employment' => ['confirmed' => 'confirmed', 'rejected' => 'rejected', 'unable_to_verify' => 'unable_to_verify'],
    ];

    public function __construct(private RequestCollector $collector) {}

    /** Tasks visible to this participant: assigned to them, and of their own check type. */
    public function tasksFor(User $participant)
    {
        return BackgroundVerification::where('participant_reference', (string) $participant->getKey())
            ->where('verification_type', self::TYPE_FOR_ROLE[$participant->role] ?? '-');
    }

    public function respond(User $participant, string $taskId, string $outcome, ?string $reference, ?string $remarks): BackgroundVerification
    {
        $task = $this->tasksFor($participant)->findOrFail($taskId);
        $type = $task->verification_type;
        $status = self::OUTCOMES[$type][$outcome] ?? null;
        if (! $status) {
            throw ValidationException::withMessages(['outcome' => 'Choose a valid response.']);
        }
        $req = VerificationRequest::where('request_id', $task->request_id)->first();

        DB::connection('mongodb')->transaction(function () use ($participant, $task, $type, $status, $outcome, $reference, $remarks, $req) {
            if (! $req || $req->request_status !== 'in_progress' || $task->due_at->isPast()) {
                throw ValidationException::withMessages(['task' => 'This request is no longer open for responses.']);
            }
            // Compare-and-set: the first response wins, replays are refused.
            $won = BackgroundVerification::where('_id', $task->getKey())->where('status', 'sent')
                ->update(['status' => $status, 'outcome' => $outcome, 'response_timestamp' => now(), 'response_reference' => $reference]);
            if ($won !== 1) {
                throw ValidationException::withMessages(['task' => 'A response was already submitted for this task.']);
            }
            $ref = (string) $participant->getKey();
            match ($type) {
                'police' => PoliceVerification::create(['request_id' => $task->request_id, 'authorized_participant_reference' => $ref, 'result_status' => $outcome, 'response_reference' => $reference, 'submitted_at' => now()]),
                'reference' => RefereeResponse::create(['request_id' => $task->request_id, 'referee_reference' => $ref, 'response' => $outcome, 'remarks' => $remarks, 'submitted_at' => now()]),
                'employment' => EmploymentVerification::create(['request_id' => $task->request_id, 'previous_employer_reference' => $ref, 'employment_status' => $outcome, 'response' => $remarks, 'submitted_at' => now()]),
            };
            AuditLogger::log('background.responded', 'verification_request', $task->request_id, ['type' => $type, 'status' => $status], $participant);
            Notifier::send($req->employer_id, 'background_responded', 'Background check response received', 'Request '.$task->request_id, route('employer.requests.show', $task->request_id));
        });
        $this->collector->refresh($task->request_id);

        return $task->fresh();
    }
}
