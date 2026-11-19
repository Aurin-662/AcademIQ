<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\VerificationReport;
use App\Services\AuditLogger;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Only the employer that owns the request can download. The file lives on a private disk: no public URL exists. */
    public function download(Request $request, ReportService $svc, string $reportId)
    {
        $report = VerificationReport::where('report_id', $reportId)->where('employer_id', (string) $request->user()->getKey())->firstOrFail();

        $bytes = $svc->bytes($report);
        if ($bytes === null) {
            AuditLogger::log('report.integrity_failure', 'report', $reportId);
            abort(500, 'Report file is unavailable. Contact support.');
        }
        AuditLogger::log('report.downloaded', 'report', $reportId, ['request_id' => $report->request_id]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="AcademiQ-'.$reportId.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
