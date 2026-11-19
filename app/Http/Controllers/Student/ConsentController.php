<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\StudentProfile;
use App\Models\VerificationRequest;
use App\Services\Verification\GatewayService;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    private function mine(Request $r)
    {
        $ref = StudentProfile::where('user_id', (string) $r->user()->getKey())->value('student_reference');

        return VerificationRequest::where('student_reference', $ref ?? '-');
    }

    public function index(Request $request)
    {
        return view('student.requests.index', ['requests' => $this->mine($request)->orderBy('created_at', 'desc')->get()]);
    }

    public function show(Request $request, GatewayService $gateway, string $requestId)
    {
        $req = $this->mine($request)->where('request_id', $requestId)->firstOrFail();

        return view('student.requests.show', ['req' => $req, 'scope' => $gateway->scopeOf($req), 'consent' => ConsentRecord::where('request_id', $requestId)->first()]);
    }

    public function decide(Request $request, GatewayService $gateway, string $requestId)
    {
        $d = $request->validate(['decision' => ['required', 'in:approve,reject'], 'scope' => ['nullable', 'array'], 'scope.*' => ['boolean']]);
        $approved = $d['decision'] === 'approve' ? ($d['scope'] ?? []) : [];
        $gateway->decide($request->user(), $requestId, $approved);

        return redirect()->route('student.requests.show', $requestId)->with('status', $d['decision'] === 'approve' ? 'Your decision was recorded.' : 'You declined this request.');
    }

    public function revoke(Request $request, GatewayService $gateway, string $requestId)
    {
        $gateway->revoke($request->user(), $requestId);

        return redirect()->route('student.requests.show', $requestId)->with('status', 'Consent withdrawn. Outstanding checks were cancelled.');
    }
}
