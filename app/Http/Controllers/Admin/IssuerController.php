<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedIssuer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Credentials\IssuerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IssuerController extends Controller
{
    public function index()
    {
        return view('admin.issuers', [
            'issuers' => AuthorizedIssuer::orderBy('created_at', 'desc')->get(),
            'candidates' => User::whereIn('role', ['admin', 'issuer'])->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, IssuerService $svc)
    {
        $d = $request->validate(['user_id' => ['required', 'string'], 'name' => ['required', 'string', 'max:120'], 'designation' => ['required', 'string', 'max:120']]);
        $user = User::whereIn('role', ['admin', 'issuer'])->findOrFail($d['user_id']);
        if (AuthorizedIssuer::where('user_id', (string) $user->getKey())->exists()) {
            return back()->withErrors(['user_id' => 'Already an authorized issuer.']);
        }
        $svc->authorize($user, $d['name'], $d['designation']);

        return back()->with('status', 'Issuer authorized and signing key generated.');
    }

    public function toggle(AuthorizedIssuer $issuer)
    {
        $issuer->update(['authorization_status' => $issuer->authorization_status === 'active' ? 'suspended' : 'active']);
        AuditLogger::log('admin.issuer_'.$issuer->authorization_status, 'authorized_issuer', $issuer->issuer_id);

        return back()->with('status', 'Issuer status updated.');
    }

    public function rotate(AuthorizedIssuer $issuer, IssuerService $svc)
    {
        $svc->rotate($issuer);

        return back()->with('status', 'Signing key rotated. Credentials signed by the old key remain valid until re-issued.');
    }
}
