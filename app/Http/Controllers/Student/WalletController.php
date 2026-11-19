<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Models\CredentialDelivery;
use App\Models\AuthorizedIssuer;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalletController extends Controller
{
    private function ref(Request $r): StudentReference
    {
        $sr = StudentProfile::where('user_id', (string) $r->user()->getKey())->value('student_reference');

        return StudentReference::where('student_reference', $sr)->firstOrFail();
    }

    public function index(Request $request)
    {
        return view('student.wallet', ['ref' => $this->ref($request)]);
    }

    /** Registers the wallet public (ECDH P-256) key. The private key never leaves the browser. */
    public function setKey(Request $request)
    {
        $d = $request->validate(['public_key_b64' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9+\/=]+$/'], 'replace' => ['nullable', 'boolean']]);
        $ref = $this->ref($request);
        if ($ref->wallet_public_key && ! $request->boolean('replace')) {
            return response()->json(['message' => 'Wallet key already registered.'], 409);
        }
        if (strlen(base64_decode($d['public_key_b64'], true) ?: '') !== 65) {
            return response()->json(['message' => 'Invalid public key.'], 422);
        }
        if ($ref->wallet_public_key) {
            CredentialDelivery::where('student_reference', $ref->student_reference)->whereNull('delivered_at')->delete(); // sealed to the old key
        }
        $ref->update(['wallet_public_key' => $d['public_key_b64']]);
        AuditLogger::log('wallet.key_registered', 'student_reference', $ref->student_reference, ['replaced' => $request->boolean('replace')]);

        return response()->json(['ok' => true]);
    }

    public function key(Request $request)
    {
        return response()->json(['public_key_b64' => $this->ref($request)->wallet_public_key]);
    }

    /** Sealed envelopes the server cannot read. */
    public function deliveries(Request $request)
    {
        return response()->json(CredentialDelivery::where('student_reference', $this->ref($request)->student_reference)->whereNull('delivered_at')->get()
            ->map(fn ($d) => ['id' => (string) $d->getKey(), 'credential_id' => $d->credential_id, 'version' => $d->version, 'envelope' => $d->envelope]));
    }

    public function ack(Request $request, string $id)
    {
        $d = CredentialDelivery::where('student_reference', $this->ref($request)->student_reference)->findOrFail($id);
        $d->delete(); // ciphertext removed once the wallet has stored the package
        AuditLogger::log('wallet.package_received', 'credential', $d->credential_id, ['version' => $d->version]);

        return response()->json(['ok' => true]);
    }

    /** Public D1 metadata for the student's own credentials. */
    public function credentials(Request $request)
    {
        $ref = $this->ref($request);

        return response()->json(Credential::where('student_reference', $ref->student_reference)->orderBy('created_at', 'desc')->get()->map(fn ($c) => $this->meta($c)));
    }

    public function show(Request $request, string $credentialId)
    {
        $c = Credential::where('credential_id', $credentialId)->firstOrFail();
        Gate::authorize('view', $c);

        return view('student.credential', ['credential' => $c, 'meta' => $this->meta($c), 'programmes' => config('academiq.programmes'), 'degrees' => config('academiq.degrees')]);
    }

    private function meta(Credential $c): array
    {
        $issuer = AuthorizedIssuer::where('issuer_id', $c->issuer_id)->first();

        return ['credential_id' => $c->credential_id, 'status' => $c->credential_status, 'version' => $c->current_version, 'issue_date' => $c->issue_date, 'commitment' => $c->commitment,
            'issuer' => $issuer ? $issuer->name.' ('.$issuer->designation.')' : $c->issuer_id, 'revoked_at' => $c->revoked_at?->toIso8601String(), 'revocation_reason' => $c->revocation_reason];
    }
}
