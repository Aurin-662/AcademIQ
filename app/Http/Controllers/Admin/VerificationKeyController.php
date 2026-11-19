<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationKey;
use App\Services\AuditLogger;

class VerificationKeyController extends Controller
{
    public function index()
    {
        return view('admin.keys', ['keys' => VerificationKey::orderBy('circuit_id')->orderByDesc('circuit_version')->get()]);
    }

    /** Retire or re-activate a circuit version. Proofs under a retired version are rejected. */
    public function toggle(VerificationKey $key)
    {
        $key->update(['status' => $key->status === 'active' ? 'retired' : 'active']);
        AuditLogger::log('admin.verification_key_'.$key->status, 'verification_key', $key->circuit_id.'_v'.$key->circuit_version);

        return back()->with('status', 'Verification key status updated.');
    }
}
