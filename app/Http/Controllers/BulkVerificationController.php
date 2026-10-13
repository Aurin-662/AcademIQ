<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkVerificationJob;
use App\Models\BulkVerificationRequest;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

class BulkVerificationController extends Controller
{
    /**
     * Show bulk upload form
     */
    public function create()
    {
        return view('bulk.create');
    }

    /**
     * Handle CSV upload and process verification
     */
    public function store(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $fileName = $file->getClientOriginalName();
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->with('error', 'Unable to read uploaded file.');
        }

        $docIds = [];
        $firstRow = fgetcsv($handle);
        if ($firstRow) {
            $val = trim($firstRow[0]);
            // If first cell looks like a header (e.g. document_id, id, certificate_id), skip it
            if (!in_array(strtolower($val), ['document_id', 'certificate_id', 'id', 'doc_id', 'roll'])) {
                if ($val !== '') {
                    $docIds[] = $val;
                }
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (isset($row[0])) {
                    $val = trim($row[0]);
                    if ($val !== '') {
                        $docIds[] = $val;
                    }
                }
                if (count($docIds) >= 5000) {
                    break;
                }
            }
        }
        fclose($handle);

        if (empty($docIds)) {
            return back()->with('error', 'The uploaded CSV contained no valid document IDs.');
        }

        $user = auth()->user();
        $verifiedBy = $user->organization ?: ($user->name . ' (' . $user->email . ')');
        $ip = $request->ip();

        // Create request record
        $bulkReq = BulkVerificationRequest::create([
            'requested_by'   => $user->email,
            'file_name'      => $fileName,
            'total_records'  => count($docIds),
            'processed'      => 0,
            'status'         => 'processing',
            'result_summary' => ['genuine' => 0, 'tampered' => 0, 'not_found' => 0, 'revoked' => 0],
            'results'        => [],
            'created_at'     => new UTCDateTime(now()->getTimestamp() * 1000),
        ]);

        $job = new ProcessBulkVerificationJob((string) $bulkReq->_id, $docIds, $verifiedBy, $ip);

        // Process synchronously by default or dispatch to queue
        if (config('queue.default') === 'sync' || env('BULK_QUEUE', false) === false) {
            $job->handle();
        } else {
            dispatch($job);
        }

        return redirect('/bulk/' . $bulkReq->_id)->with('success', 'Bulk verification processed successfully!');
    }

    /**
     * Show verification results summary and table
     */
    public function show(string $id)
    {
        $bulkReq = BulkVerificationRequest::where('_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        // Authorization check: requested_by must match or user is system_admin
        if (auth()->user()->role !== 'system_admin' && auth()->user()->email !== $bulkReq->requested_by) {
            abort(403, 'Unauthorized access to this bulk verification report.');
        }

        return view('bulk.show', compact('bulkReq'));
    }

    /**
     * Download verification results as CSV
     */
    public function download(string $id)
    {
        $bulkReq = BulkVerificationRequest::where('_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        if (auth()->user()->role !== 'system_admin' && auth()->user()->email !== $bulkReq->requested_by) {
            abort(403, 'Unauthorized access.');
        }

        $results = $bulkReq->results ?? [];
        $fileName = 'verification_results_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $bulkReq->file_name);
        if (!str_ends_with($fileName, '.csv')) {
            $fileName .= '.csv';
        }

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($results) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Document ID', 'Recipient Name', 'Verification Result', 'Audited By']);

            foreach ($results as $row) {
                fputcsv($file, [
                    $row['document_id'] ?? '',
                    $row['recipient_name'] ?? '',
                    strtoupper($row['result'] ?? 'UNKNOWN'),
                    'AcademIQ HMAC-SHA256 Engine',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * View employer's bulk verification history
     */
    public function history()
    {
        $user = auth()->user();
        $query = BulkVerificationRequest::query();

        if ($user->role !== 'system_admin') {
            $query->where('requested_by', $user->email);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('bulk.history', compact('requests'));
    }
}
