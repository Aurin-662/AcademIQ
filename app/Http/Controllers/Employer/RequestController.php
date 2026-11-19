<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VerificationReport;
use App\Models\BackgroundVerification;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\Verification\ClaimDescriber;
use App\Services\Verification\GatewayService;
use App\Services\Verification\RequestCollector;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $reqs = VerificationRequest::where('employer_id', (string) $request->user()->getKey())->orderBy('created_at', 'desc')->get();

        return view('employer.dashboard', [
            'requests' => $reqs,
            'counts' => ['pending' => $reqs->whereIn('request_status', ['awaiting_consent', 'in_progress'])->count(), 'completed' => $reqs->where('request_status', 'completed')->count()],
        ]);
    }

    public function create()
    {
        return view('employer.requests.create', [
            'police' => $this->participants('police_verifier'), 'referees' => $this->participants('referee'), 'employers' => $this->participants('previous_employer'),
        ]);
    }

    public function store(Request $request, GatewayService $gateway)
    {
        $d = $request->validate([
            'student_reference' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'academic_kind' => ['nullable', Rule::in(['cgpa', 'programme', 'degree', 'issuer'])],
            'cgpa_min' => ['required_if:academic_kind,cgpa', 'nullable', 'regex:/^(?:[0-3](?:\.\d{1,2})?|4(?:\.00?)?)$/'],
            'programme_code' => ['required_if:academic_kind,programme', 'nullable', Rule::in(array_keys(config('academiq.programmes')))],
            'degree_category' => ['required_if:academic_kind,degree', 'nullable', Rule::in(array_keys(config('academiq.degrees')))],
            'police' => ['nullable', 'boolean'], 'police_participant' => ['required_if:police,1', 'nullable', 'string'],
            'police_basis' => ['required_if:police,1', 'nullable', 'string', 'min:10', 'max:300'],
            'reference' => ['nullable', 'boolean'], 'reference_participant' => ['required_if:reference,1', 'nullable', 'string'],
            'reference_position' => ['required_if:reference,1', 'nullable', 'string', 'max:160'],
            'employment' => ['nullable', 'boolean'], 'employment_participant' => ['required_if:employment,1', 'nullable', 'string'],
            'employment_company' => ['required_if:employment,1', 'nullable', 'string', 'max:160'],
            'employment_title' => ['required_if:employment,1', 'nullable', 'string', 'max:160'],
            'employment_period' => ['required_if:employment,1', 'nullable', 'string', 'max:80'],
        ]);
        $req = $gateway->receive($request->user(), $d + ['police' => $request->boolean('police'), 'reference' => $request->boolean('reference'), 'employment' => $request->boolean('employment')]);

        return redirect()->route('employer.requests.show', $req->request_id)->with('status', 'Request submitted. The applicant has been asked for consent.');
    }

    public function show(Request $request, RequestCollector $collector, string $requestId)
    {
        // Scoped to the owning employer: another employer's id yields 404.
        $req = VerificationRequest::where('request_id', $requestId)->where('employer_id', (string) $request->user()->getKey())->firstOrFail();
        $consent = ConsentRecord::where('request_id', $requestId)->first();

        $report = VerificationReport::where('request_id', $requestId)->first();
        $accesses = $report ? AuditLog::where('entity_type', 'report')->where('entity_reference', $report->report_id)->where('action', 'report.downloaded')->orderByDesc('timestamp')->limit(20)->get()
            ->each(fn ($a) => $a->actor_name = User::find($a->actor_id)?->name ?? 'unknown') : collect();

        return view('employer.requests.show', ['req' => $req, 'rows' => $collector->summary($req), 'consent' => $consent, 'report' => $report, 'accesses' => $accesses]);
    }

    private function participants(string $role)
    {
        return User::where('role', $role)->where('is_active', true)->orderBy('name')->get(['name']);
    }
}
