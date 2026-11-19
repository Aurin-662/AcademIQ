<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\ZKP\ProofService;
use Illuminate\Http\Request;

class ProofController extends Controller
{
    /** Holder-side proving page for a gateway-authorized request. */
    public function show(Request $request, ProofService $svc, string $requestId)
    {
        abort_unless($svc->instruction($request->user(), $requestId), 404);

        return view('student.prove', ['requestId' => $requestId]);
    }

    public function instruction(Request $request, ProofService $svc, string $requestId)
    {
        return response()->json($svc->instruction($request->user(), $requestId) ?? abort(404));
    }

    /** Receives ONLY the proof and public signals. */
    public function submit(Request $request, ProofService $svc)
    {
        $d = $request->validate([
            'request_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'credential_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'circuit_version' => ['required', 'integer', 'min:1'],
            'proof' => ['required', 'array'],
            'proof.pi_a' => ['required', 'array', 'size:3'], 'proof.pi_a.*' => ['required', 'string', 'regex:/^\d{1,80}$/'],
            'proof.pi_b' => ['required', 'array', 'size:3'], 'proof.pi_b.*' => ['required', 'array', 'size:2'], 'proof.pi_b.*.*' => ['required', 'string', 'regex:/^\d{1,80}$/'],
            'proof.pi_c' => ['required', 'array', 'size:3'], 'proof.pi_c.*' => ['required', 'string', 'regex:/^\d{1,80}$/'],
            'proof.protocol' => ['required', 'in:groth16'],
            'proof.curve' => ['required', 'in:bn128'],
            'public_signals' => ['required', 'array', 'size:6'], 'public_signals.*' => ['required', 'string', 'regex:/^\d{1,80}$/'],
        ]);
        // Defence in depth: refuse anything that looks like private witness material.
        foreach (['salt', 'signature', 'R8', 'fields', 'claims', 'cgpa'] as $forbidden) {
            abort_if($request->has($forbidden), 422, 'Private data must not be submitted.');
        }

        $r = $svc->submit($request->user(), $d);

        return response()->json($r, $r['result'] === 'REJECTED' ? 403 : 200);
    }
}
