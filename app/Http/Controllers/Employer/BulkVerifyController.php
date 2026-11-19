<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVerificationBatch;
use App\Models\VerificationBatch;
use App\Models\VerificationBatchItem;
use App\Services\AuditLogger;
use App\Services\Credentials\BulkImport;
use App\Services\Verification\BatchVerifier;
use App\Services\Verification\TokenVerifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** 4.1 to 4.6: criteria once, many tokens, progress, filterable results, CSV/PDF export. */
class BulkVerifyController extends Controller
{
    private const FILTERS = ['all' => null, 'passed' => 'PASS', 'failed' => 'FAIL', 'not_proven' => 'NOT PROVEN', 'invalid' => 'INVALID', 'duplicate' => 'DUPLICATE'];
    private const SORTS = ['row', 'overall', 'token', 'candidate'];

    public function index(Request $request)
    {
        return view('employer.bulk.index', ['max' => config('academiq.bulk_max_tokens'), 'minYear' => config('academiq.min_passing_year'), 'maxYear' => now()->year,
            'ladder' => array_map(fn ($x) => number_format($x / 100, 2), \App\Services\ZKP\TokenService::LADDER),
            'recent' => VerificationBatch::where('verifier_id', (string) $request->user()->getKey())->orderBy('created_at', 'desc')->limit(10)->get()]);
    }

    public function store(Request $request, BatchVerifier $svc)
    {
        $request->validate(VerifyController::rules() + ['file' => ['nullable', 'file', 'max:2048', 'extensions:csv,txt,xlsx'], 'tokens' => ['nullable', 'string', 'max:100000'], 'scanned' => ['nullable', 'string', 'max:100000']]);
        $criteria = TokenVerifier::criteria($request->only(array_keys(VerifyController::rules())));
        $rows = array_merge(
            $request->hasFile('file') ? $this->fileRows($request->file('file')) : [],
            $this->listRows((string) $request->input('tokens')),
            $this->listRows((string) $request->input('scanned')),
        );
        $batch = $svc->create($request->user(), $criteria, $rows);
        // With a real queue worker the batch runs in the background; with the sync driver the progress page drives it in small steps.
        if ($batch->status !== 'done' && config('queue.default') !== 'sync') {
            ProcessVerificationBatch::dispatch($batch->batch_id);
        }

        return redirect()->route('employer.bulk.show', $batch->batch_id);
    }

    public function show(Request $request, BatchVerifier $svc, string $batchId)
    {
        $b = $this->batch($request, $batchId);
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'all';
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'row';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $items = VerificationBatchItem::where('batch_id', $batchId)->orderBy('row')->get();
        if (self::FILTERS[$filter]) {
            $items = $items->where('overall', self::FILTERS[$filter]);
        }
        $key = ['row' => 'row', 'overall' => 'overall', 'token' => 'token_id', 'candidate' => 'candidate_name'][$sort];
        $items = ($dir === 'desc' ? $items->sortByDesc($key) : $items->sortBy($key))->values();

        return view('employer.bulk.show', ['b' => $b, 'p' => $svc->progress($b), 'items' => $items, 'filter' => $filter, 'sort' => $sort, 'dir' => $dir,
            'sync' => config('queue.default') === 'sync', 'filters' => array_keys(self::FILTERS)]);
    }

    public function progress(Request $request, BatchVerifier $svc, string $batchId)
    {
        return response()->json($svc->progress($this->batch($request, $batchId)));
    }

    /** Advances a batch by one small chunk. Used by the progress page when no queue worker is configured. */
    public function step(Request $request, BatchVerifier $svc, string $batchId)
    {
        return response()->json($svc->step($this->batch($request, $batchId)));
    }

    public function csv(Request $request, string $batchId)
    {
        $b = $this->batch($request, $batchId);
        $items = VerificationBatchItem::where('batch_id', $batchId)->orderBy('row')->get();
        AuditLogger::log('bulk.exported', 'verification_batch', $batchId, ['format' => 'csv'], $request->user());

        return response()->streamDownload(function () use ($b, $items) {
            $o = fopen('php://output', 'w');
            $labels = array_column($b->criteria, 'label');
            fputcsv($o, array_merge(['row', 'token_id', 'candidate_name', 'cv_ref'], $labels, ['overall', 'reason']), ',', '"', '');
            foreach ($items as $i) {
                $byKey = array_column($i->results ?? [], 'result', 'key');
                $cells = array_map(fn ($c) => $byKey[$c['key']] ?? '', $b->criteria);
                fputcsv($o, array_map([$this, 'safe'], array_merge([$i->row, $i->token_id ?? $i->input, $i->candidate_name, $i->cv_ref], $cells, [$i->overall, BatchVerifier::reasonText($i->reason)])), ',', '"', '');
            }
            fclose($o);
        }, "academiq-batch-{$batchId}.csv", ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request, BatchVerifier $svc, string $batchId)
    {
        $b = $this->batch($request, $batchId);
        AuditLogger::log('bulk.exported', 'verification_batch', $batchId, ['format' => 'pdf'], $request->user());

        return Pdf::loadView('employer.bulk.report', ['b' => $b, 'p' => $svc->progress($b), 'items' => VerificationBatchItem::where('batch_id', $batchId)->orderBy('row')->get()])
            ->setPaper('a4', 'landscape')->download("academiq-batch-{$batchId}.pdf");
    }

    /** CSV injection guard: spreadsheet apps execute cells starting with = + - @. */
    public function safe(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }

    private function batch(Request $request, string $batchId): VerificationBatch
    {
        return VerificationBatch::where('batch_id', $batchId)->where('verifier_id', (string) $request->user()->getKey())->firstOrFail();
    }

    /** CSV/XLSX with a token_id column; candidate_name and cv_ref optional. */
    private function fileRows($file): array
    {
        $grid = app(BulkImport::class)->grid($file);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), array_shift($grid) ?? []);
        $col = array_flip($header);
        if (! isset($col['token_id'])) {
            throw ValidationException::withMessages(['file' => 'The file needs a token_id column (candidate_name and cv_ref are optional).']);
        }
        $rows = [];
        foreach ($grid as $cells) {
            $t = trim((string) ($cells[$col['token_id']] ?? ''));
            if ($t !== '') {
                $rows[] = ['token' => $t, 'name' => $cells[$col['candidate_name'] ?? -1] ?? '', 'cv_ref' => $cells[$col['cv_ref'] ?? -1] ?? ''];
            }
        }

        return $rows;
    }

    /** One token per line, optionally "token, name, cv_ref". */
    private function listRows(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$t, $name, $ref] = array_pad(array_map('trim', preg_split('/[,\t;]/', $line, 3)), 3, '');
            $rows[] = ['token' => $t, 'name' => $name, 'cv_ref' => $ref];
        }

        return $rows;
    }
}
