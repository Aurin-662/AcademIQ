<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['group' => ['nullable', 'string', 'max:20', 'regex:/^[a-z_]+$/'], 'ref' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/']]);
        $q = AuditLog::orderByDesc('timestamp');
        if (! empty($f['group'])) {
            $q->where('action', 'like', $f['group'].'.%'); // validated: no regex metacharacters reach the query
        }
        if (! empty($f['ref'])) {
            $q->where('entity_reference', $f['ref']);
        }

        return view('admin.audit', [
            'logs' => $q->paginate(30)->withQueryString(),
            'groups' => ['auth', 'credential', 'issuer', 'wallet', 'request', 'consent', 'proof', 'background', 'report', 'admin'],
            'filters' => $f,
        ]);
    }
}
