<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

/** Public authenticity check. Discloses only that the pair is valid, the request id and the generation date. */
class ReportVerifyController extends Controller
{
    public function check(Request $request, ReportService $svc)
    {
        $d = $request->validate(['report_id' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'], 'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9]+$/']]);
        $r = $svc->verifyCode(strtoupper($d['report_id']), $d['code']);
        AuditLogger::log($r ? 'report.authenticity_confirmed' : 'report.authenticity_failed', 'report', strtoupper($d['report_id']));

        return back()->withInput()->with('result', $r ? ['valid' => true, 'request_id' => $r->request_id, 'generated_at' => $r->generated_at->format('Y-m-d H:i')] : ['valid' => false]);
    }
}
