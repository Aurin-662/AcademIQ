<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\VerificationReport;
use App\Models\VerificationRequest;
use App\Models\UserNotification;

class DashboardController extends Controller
{
    public function home()
    {
        return redirect(auth()->user()->roleEnum()->home());
    }

    public function admin()
    {
        // Issuance activity for the last 14 days, from the audit log (real events only).
        $since = now()->subDays(13)->startOfDay();
        $byDay = AuditLog::where('action', 'credential.issued')->where('timestamp', '>=', $since)->get()
            ->groupBy(fn ($l) => $l->timestamp->format('Y-m-d'))->map->count();
        $activity = collect(range(0, 13))->map(fn ($i) => ['day' => now()->subDays(13 - $i)->format('Y-m-d'), 'count' => 0]);
        $activity = $activity->map(fn ($a) => ['day' => $a['day'], 'count' => (int) ($byDay[$a['day']] ?? 0)]);

        return view('admin.dashboard', [
            'stats' => [
                'Active credentials' => Credential::where('credential_status', 'active')->count(),
                'Revoked credentials' => Credential::where('credential_status', 'revoked')->count(),
                'Registered students' => StudentReference::count(),
                'Authorized issuers' => AuthorizedIssuer::where('authorization_status', 'active')->count(),
                'Verification requests' => VerificationRequest::count(),
                'Completed verifications' => VerificationRequest::where('request_status', 'completed')->count(),
                'Reports generated' => VerificationReport::count(),
            ],
            'activity' => $activity,
            'recent' => AuditLog::orderByDesc('timestamp')->limit(8)->get(),
        ]);
    }

    public function student()
    {
        $ref = StudentProfile::where('user_id', (string) auth()->id())->value('student_reference') ?? '-';

        return view('student.dashboard', [
            'pending' => VerificationRequest::where('student_reference', $ref)->where('request_status', 'awaiting_consent')->orderBy('created_at', 'desc')->get(),
            'proofs' => VerificationRequest::where('student_reference', $ref)->where('request_status', 'in_progress')->where('academic_state', 'pending')->get(),
            'credentials' => Credential::where('student_reference', $ref)->where('credential_status', 'active')->count(),
            'history' => VerificationRequest::where('student_reference', $ref)->whereNotIn('request_status', ['awaiting_consent'])->orderBy('created_at', 'desc')->limit(5)->get(),
            'notes' => UserNotification::where('user_id', (string) auth()->id())->orderByDesc('created_at')->limit(5)->get(),
        ]);
    }
}
