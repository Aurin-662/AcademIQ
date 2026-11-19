<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\TokenUsageLog;
use App\Models\VerificationToken;
use App\Services\ZKP\TokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** 2.2 to 2.6: claim selection, token generation (proof made in the browser), token management. */
class TokenController extends Controller
{
    public function index(Request $request)
    {
        return view('student.tokens.index', ['tokens' => VerificationToken::where('student_reference', $this->ref($request))->whereIn('status', ['active', 'revoked'])->orderBy('created_at', 'desc')->get()]);
    }

    public function create(Request $request)
    {
        return view('student.tokens.create', ['ref' => \App\Models\StudentReference::where('student_reference', $this->ref($request))->firstOrFail(),
            'minYear' => config('academiq.min_passing_year'), 'maxYear' => now()->year]);
    }

    public function prepare(Request $request, TokenService $svc)
    {
        $d = $request->validate([
            'credential_id' => ['required', 'string', 'max:40'],
            'expiry_days' => ['required', 'integer', 'between:1,365'],
            'cgpa' => ['nullable', 'array'], 'cgpa.mode' => ['required_with:cgpa', Rule::in(['threshold', 'reveal'])],
            'year' => ['nullable', 'array'],
            'year.lo' => ['required_with:year', 'integer', 'between:'.config('academiq.min_passing_year').','.now()->year],
            'year.hi' => ['required_with:year', 'integer', 'gte:year.lo', 'between:'.config('academiq.min_passing_year').','.now()->year],
            'institution' => ['nullable', 'array'], 'institution.code' => ['required_with:institution', 'integer', Rule::in(array_keys(config('academiq.institutions')))],
            'degree' => ['nullable', 'array'], 'degree.code' => ['required_with:degree', 'integer', Rule::in(array_keys(config('academiq.degrees')))],
            'department' => ['nullable', 'array'], 'department.code' => ['required_with:department', 'integer', Rule::in(array_keys(config('academiq.programmes')))],
        ]);

        return response()->json($svc->prepare($request->user(), $d));
    }

    /** Receives ONLY the proof and public signals. */
    public function submit(Request $request, TokenService $svc)
    {
        $d = $request->validate([
            'token_id' => ['required', 'string', 'regex:/^ACQ(-[0-9A-Z]{4}){4}$/'], 'credential_id' => ['required', 'string', 'max:40'],
            'proof' => ['required', 'array'], 'proof.pi_a' => ['required', 'array'], 'proof.pi_b' => ['required', 'array'], 'proof.pi_c' => ['required', 'array'],
            'public_signals' => ['required', 'array', 'size:27'], 'public_signals.*' => ['required', 'string', 'max:100'],
        ]);
        $res = $svc->submit($request->user(), $d['token_id'], $d['credential_id'], $d['proof'], $d['public_signals']);

        return response()->json($res, $res['result'] === 'OK' ? 200 : 422);
    }

    public function show(Request $request, string $tokenId)
    {
        $t = $this->own($request, $tokenId);

        return view('student.tokens.show', ['t' => $t, 'logs' => TokenUsageLog::where('token_id', $tokenId)->orderBy('created_at', 'desc')->limit(100)->get(), 'link' => url('/v/'.$t->token_id)]);
    }

    public function revoke(Request $request, TokenService $svc, string $tokenId)
    {
        $svc->revoke($request->user(), $tokenId);

        return redirect()->route('student.tokens.show', $tokenId)->with('status', 'Token revoked. Verifiers can no longer use it.');
    }

    private function ref(Request $r): string
    {
        return StudentProfile::where('user_id', (string) $r->user()->getKey())->value('student_reference') ?? abort(403);
    }

    private function own(Request $r, string $tokenId): VerificationToken
    {
        return VerificationToken::where('token_id', $tokenId)->where('student_reference', $this->ref($r))->whereIn('status', ['active', 'revoked'])->firstOrFail();
    }
}
