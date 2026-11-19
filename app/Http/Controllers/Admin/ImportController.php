<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Models\IssuanceBatch;
use App\Services\Credentials\BulkImport;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** 1.1, 1.3, 1.4, 1.5: bulk import with preview, batch commit, result + error report, bulk revoke. */
class ImportController extends Controller
{
    public function index()
    {
        return view('admin.import.index');
    }

    public function template()
    {
        return response()->streamDownload(fn () => print (BulkImport::template()), 'academiq-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request, BulkImport $import)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:csv,txt,xlsx']]);
        $res = $import->validate($import->parse($request->file('file')));
        $id = (string) Str::uuid();
        // Staged encrypted for 30 min so commit never trusts browser-posted values; deleted on commit.
        Cache::put("import:$id", Crypt::encryptString(json_encode(['user' => (string) $request->user()->getKey(), 'valid' => $res['valid'], 'invalid' => $res['invalid']])), now()->addMinutes(30));

        return view('admin.import.preview', ['importId' => $id, 'preview' => $res['preview'], 'validCount' => count($res['valid']), 'invalidCount' => count($res['invalid'])]);
    }

    public function commit(Request $request, CredentialService $svc)
    {
        $d = $request->validate(['import_id' => ['required', 'uuid']]);
        $enc = Cache::pull("import:{$d['import_id']}"); // atomic: a double submit finds nothing
        $staged = $enc ? json_decode(Crypt::decryptString($enc), true) : null;
        if (! $staged || $staged['user'] !== (string) $request->user()->getKey()) {
            throw ValidationException::withMessages(['file' => 'This import expired or was already committed. Upload the file again.']);
        }
        $batch = $svc->issueBatch($request->user(), $staged['valid'], $staged['invalid'], 'BATCH-'.Str::upper(Str::random(10)));

        return redirect()->route('admin.import.result', $batch->batch_id);
    }

    public function result(Request $request, string $batchId)
    {
        return view('admin.import.result', ['batch' => $this->batch($request, $batchId)]);
    }

    public function errorReport(Request $request, string $batchId)
    {
        $b = $this->batch($request, $batchId);

        return response()->streamDownload(function () use ($b) {
            $o = fopen('php://output', 'w');
            fputcsv($o, ['row', 'student_id', 'errors'], ',', '"', '');
            foreach ($b->errors ?? [] as $e) {
                fputcsv($o, [$e['row'], $e['student_id'], implode('; ', $e['errors'])], ',', '"', '');
            }
            fclose($o);
        }, "errors-{$b->batch_id}.csv", ['Content-Type' => 'text/csv']);
    }

    /** 1.5 bulk revoke: every active credential of each listed student. */
    public function revoke(Request $request, CredentialService $svc)
    {
        $d = $request->validate(['student_ids' => ['required', 'string', 'max:20000'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $ids = array_values(array_unique(preg_split('/[\s,;]+/', trim($d['student_ids']), -1, PREG_SPLIT_NO_EMPTY)));
        if (count($ids) > config('academiq.import_max_rows')) {
            throw ValidationException::withMessages(['student_ids' => 'Too many IDs (max '.config('academiq.import_max_rows').').']);
        }
        $revoked = 0;
        $none = [];
        foreach ($ids as $ref) {
            $creds = Credential::where('student_reference', $ref)->where('credential_status', 'active')->get();
            $creds->isEmpty() && $none[] = $ref;
            foreach ($creds as $c) {
                $svc->revoke($request->user(), $c->credential_id, $d['reason']);
                $revoked++;
            }
        }

        return back()->with('status', "Revoked $revoked credential(s)."
            .($none ? ' No active credential for: '.implode(', ', array_slice($none, 0, 20)).(count($none) > 20 ? '…' : '').'.' : ''));
    }

    private function batch(Request $request, string $batchId): IssuanceBatch
    {
        $b = IssuanceBatch::where('batch_id', $batchId)->firstOrFail();
        abort_unless($request->user()->hasRole('admin') || $b->issued_by === (string) $request->user()->getKey(), 403);

        return $b;
    }
}
