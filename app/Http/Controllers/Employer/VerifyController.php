<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\TokenUsageLog;
use App\Models\VerificationToken;
use App\Services\Verification\TokenVerifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** 3.1 to 3.6: open a token by id / link / QR, choose criteria, see PASS / FAIL / NOT PROVEN, print or save a report. */
class VerifyController extends Controller
{
    public function index()
    {
        return view('employer.verify.index');
    }

    /** Typed id, pasted link or decoded QR text all land here. */
    public function open(Request $request)
    {
        $d = $request->validate(['input' => ['required', 'string', 'max:500']]);
        $id = TokenVerifier::extract($d['input']);
        if (! $id) {
            throw ValidationException::withMessages(['input' => 'No AcademiQ token found in that text.']);
        }

        return redirect()->route('employer.verify.show', $id);
    }

    /** Criteria builder. Offers only what the token proves; never any hidden value. */
    public function show(string $tokenId)
    {
        $t = VerificationToken::where('token_id', $tokenId)->whereIn('status', ['active', 'revoked'])->first();
        $problem = ! $t ? 'unknown_token' : ($t->status === 'revoked' ? 'revoked' : ($t->expires_at?->isPast() ? 'expired' : null));

        return view('employer.verify.show', ['tokenId' => $tokenId, 't' => $t, 'problem' => $problem, 'problemText' => TokenVerifier::reasonText($problem),
            'minYear' => config('academiq.min_passing_year'), 'maxYear' => now()->year, 'ladder' => array_map(fn ($x) => number_format($x / 100, 2), \App\Services\ZKP\TokenService::LADDER)]);
    }

    public function check(Request $request, TokenVerifier $v, string $tokenId)
    {
        $criteria = TokenVerifier::criteria($this->validated($request));
        if (! $criteria) {
            throw ValidationException::withMessages(['criteria' => 'Choose at least one criterion.']);
        }
        $r = $v->check($request->user(), $tokenId, $criteria);

        return redirect()->route('employer.verify.result', $r['check_id']);
    }

    public function result(Request $request, string $checkId)
    {
        return view('employer.verify.result', ['log' => $this->log($request, $checkId), 'tokenId' => null]);
    }

    public function pdf(Request $request, string $checkId)
    {
        $log = $this->log($request, $checkId);

        return Pdf::loadView('employer.verify.report', ['log' => $log, 'pdf' => true])->setPaper('a4')->download("academiq-check-{$checkId}.pdf");
    }

    /** Shared with bulk verification (Phase 4). */
    public static function rules(): array
    {
        return [
            'cgpa_min' => ['nullable', Rule::in(['2.00', '2.50', '3.00', '3.25', '3.50', '3.75', '4.00'])],
            'year_from' => ['nullable', 'integer', 'between:'.config('academiq.min_passing_year').','.now()->year],
            'year_to' => ['nullable', 'integer', 'between:'.config('academiq.min_passing_year').','.now()->year, 'gte:year_from'],
            'institution' => ['nullable', 'integer', Rule::in(array_keys(config('academiq.institutions')))],
            'degree' => ['nullable', 'integer', Rule::in(array_keys(config('academiq.degrees')))],
            'department' => ['nullable', 'integer', Rule::in(array_keys(config('academiq.programmes')))],
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate(self::rules());
    }

    private function log(Request $request, string $checkId): TokenUsageLog
    {
        return TokenUsageLog::where('verifier_id', (string) $request->user()->getKey())->findOrFail($checkId);
    }
}
