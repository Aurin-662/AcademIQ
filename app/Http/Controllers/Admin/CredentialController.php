<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\StudentReference;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CredentialController extends Controller
{
    public function index()
    {
        return view('admin.credentials.index', ['credentials' => Credential::orderBy('created_at', 'desc')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.credentials.form', ['credential' => null]);
    }

    public function store(Request $request, CredentialService $svc)
    {
        $d = $request->validate($this->rules(true));
        $cred = $svc->issue($request->user(), $d);

        return redirect()->route('admin.credentials.show', $cred->credential_id)->with('status', 'Credential issued and sealed to the student wallet.');
    }

    public function show(string $credentialId)
    {
        $cred = Credential::where('credential_id', $credentialId)->firstOrFail();
        Gate::authorize('view', $cred);

        return view('admin.credentials.show', ['credential' => $cred, 'versions' => CredentialVersion::where('credential_id', $credentialId)->orderBy('version')->get()]);
    }

    public function edit(string $credentialId)
    {
        return view('admin.credentials.form', ['credential' => Credential::where('credential_id', $credentialId)->firstOrFail()]);
    }

    public function update(Request $request, string $credentialId, CredentialService $svc)
    {
        $d = $request->validate($this->rules(false));
        $svc->update($request->user(), $credentialId, $d);

        return redirect()->route('admin.credentials.show', $credentialId)->with('status', 'New version issued. Previous version superseded.');
    }

    public function revoke(Request $request, string $credentialId, CredentialService $svc)
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $svc->revoke($request->user(), $credentialId, $d['reason']);

        return redirect()->route('admin.credentials.show', $credentialId)->with('status', 'Credential revoked.');
    }

    private function rules(bool $withStudent): array
    {
        return [
            'student_reference' => $withStudent ? ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/'] : ['prohibited'],
            'cgpa' => ['required', 'regex:/^(?:[0-3](?:\.\d{1,2})?|4(?:\.00?)?)$/'],
            'programme_code' => ['required', 'integer', Rule::in(array_keys(config('academiq.programmes')))],
            'degree_category' => ['required', 'integer', Rule::in(array_keys(config('academiq.degrees')))],
            // nullable: the form always sends them; the service defaults (KUET, year of issue_date) keep API/test callers working.
            'institution_code' => ['nullable', 'integer', Rule::in(array_keys(config('academiq.institutions')))],
            'passing_year' => ['nullable', 'integer', 'between:'.config('academiq.min_passing_year').','.now()->year],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
