<?php

namespace App\Services\Reports;

use App\Models\BackgroundVerification;
use App\Models\ConsentRecord;
use App\Models\VerificationReport;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Services\Verification\ClaimDescriber;
use App\Services\Verification\GatewayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Process 5.1 (collect) + 5.2 (generate). The report carries OUTCOMES ONLY.
 * It never contains credential contents, issuer signature, salt, proofs, witnesses, participant remarks,
 * police case references, or results of checks the applicant did not consent to.
 */
class ReportService
{
    private const DISK = 'local'; // storage/app/private, never web-served

    /** Idempotent: one report per request. */
    public function generate(string $requestId): ?VerificationReport
    {
        if ($existing = VerificationReport::where('request_id', $requestId)->first()) {
            return $existing;
        }
        $req = VerificationRequest::where('request_id', $requestId)->first();
        if (! $req || $req->request_status !== 'completed') {
            return null;
        }

        $reportId = 'RPT-'.Str::upper(Str::random(12));
        $generatedAt = now();
        $data = $this->collect($req);
        $code = $this->authCode($reportId, $requestId, $data['outcomes'], $data['overall']);
        $html = view('reports.verification', $data + ['reportId' => $reportId, 'generatedAt' => $generatedAt, 'authCode' => $code])->render();

        $path = "reports/{$reportId}.pdf";
        $bytes = Pdf::loadHTML($html)->setPaper('a4')->output();
        Storage::disk(self::DISK)->put($path, $bytes);

        $report = VerificationReport::create([
            'report_id' => $reportId, 'request_id' => $requestId, 'employer_id' => $req->employer_id, 'applicant_reference' => $req->student_reference,
            'outcome_summary' => ['overall' => $data['overall'], 'outcomes' => $data['outcomes']], 'report_file_reference' => $path,
            'sha256' => hash('sha256', $bytes), 'auth_code' => $code, 'generated_at' => $generatedAt,
        ]);
        AuditLogger::log('report.generated', 'report', $reportId, ['request_id' => $requestId]);
        Notifier::send($req->employer_id, 'report_generated', 'Verification report ready', 'Request '.$requestId, route('employer.requests.show', $requestId));
        Notifier::send($req->student_user_id, 'report_generated', 'A verification report was generated', 'Request '.$requestId, route('student.requests.show', $requestId));

        return $report;
    }

    /** Rendered HTML of the report body (also used by tests to assert content). */
    public function collect(VerificationRequest $req): array
    {
        $consent = ConsentRecord::where('request_id', $req->request_id)->first();
        $scope = $consent?->approved_scope ?? [];
        $outcomes = [];
        $judgements = [];

        if (! empty($req->requested_checks['academic'])) {
            $label = ClaimDescriber::academic($req->requested_checks['academic']);
            if (empty($scope['academic'])) {
                $outcomes[] = ['check' => $label, 'type' => 'Academic', 'result' => 'Not consented by applicant'];
                $judgements[] = 'incomplete';
            } elseif ($req->academic_state === 'done') {
                $ok = $req->academic_result === 'TRUE';
                $outcomes[] = ['check' => $label, 'type' => 'Academic', 'result' => $ok ? 'Verified (zero-knowledge proof)' : 'Not verified'];
                $judgements[] = $ok ? 'positive' : 'negative';
            } else {
                $outcomes[] = ['check' => $label, 'type' => 'Academic', 'result' => 'Not completed'];
                $judgements[] = 'incomplete';
            }
        }
        foreach ($req->requested_checks['background'] as $t) {
            $type = $t['type'];
            $name = ClaimDescriber::backgroundLabel($type);
            if (empty($scope[$type])) {
                $outcomes[] = ['check' => $name, 'type' => 'Background', 'result' => 'Not consented by applicant'];
                $judgements[] = 'incomplete';
                continue;
            }
            $task = BackgroundVerification::where('request_id', $req->request_id)->where('verification_type', $type)->first();
            [$text, $j] = $this->background($type, $task);
            $outcomes[] = ['check' => $name, 'type' => 'Background', 'result' => $text];
            $judgements[] = $j;
        }

        $overall = in_array('negative', $judgements, true) ? 'Not verified' : (in_array('incomplete', $judgements, true) ? 'Incomplete' : 'Verified');

        return [
            'request' => $req, 'consent' => $consent, 'outcomes' => $outcomes, 'overall' => $overall,
            'approvedLabels' => collect($outcomes)->filter(fn ($o) => $o['result'] !== 'Not consented by applicant')->pluck('check')->all(),
        ];
    }

    /** @return array{0:string,1:string} [label, positive|negative|incomplete] */
    private function background(string $type, ?BackgroundVerification $task): array
    {
        if (! $task) {
            return ['Not completed', 'incomplete'];
        }
        $o = $task->outcome;

        return match (true) {
            $task->status === 'expired' => ['No response received (expired)', 'incomplete'],
            $task->status === 'cancelled' => ['Cancelled', 'incomplete'],
            $o === 'unable_to_verify' || $o === 'decline_to_comment' => ['Unable to verify', 'incomplete'],
            $type === 'police' && $o === 'clear' => ['Clear', 'positive'],
            $type === 'police' && $o === 'adverse_record' => ['Adverse record reported', 'negative'],
            $type === 'reference' && $o === 'recommend' => ['Recommended', 'positive'],
            $type === 'reference' && $o === 'recommend_with_reservations' => ['Recommended with reservations', 'positive'],
            $type === 'reference' && $o === 'not_recommend' => ['Not recommended', 'negative'],
            $type === 'employment' && $o === 'confirmed' => ['Employment confirmed', 'positive'],
            $type === 'employment' && $o === 'rejected' => ['Employment details rejected', 'negative'],
            default => ['Not completed', 'incomplete'],
        };
    }

    /** Authenticity code: HMAC over the report's outcome content, keyed with the server secret. */
    public function authCode(string $reportId, string $requestId, array $outcomes, string $overall): string
    {
        return strtoupper(substr(hash_hmac('sha256', json_encode([$reportId, $requestId, $outcomes, $overall]), config('app.key')), 0, 16));
    }

    /** Public authenticity check: confirms a report id + code pair exists. Returns minimal facts only. */
    public function verifyCode(string $reportId, string $code): ?VerificationReport
    {
        $r = VerificationReport::where('report_id', $reportId)->first();

        return $r && hash_equals((string) $r->auth_code, strtoupper(trim($code))) ? $r : null;
    }

    /** Returns file bytes only if the stored hash still matches (tamper check). */
    public function bytes(VerificationReport $report): ?string
    {
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($report->report_file_reference)) {
            return null;
        }
        $bytes = $disk->get($report->report_file_reference);

        return hash_equals($report->sha256, hash('sha256', $bytes)) ? $bytes : null;
    }
}
