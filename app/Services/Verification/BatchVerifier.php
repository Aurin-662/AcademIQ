<?php

namespace App\Services\Verification;

use App\Models\EmployerProfile;
use App\Models\User;
use App\Models\VerificationBatch;
use App\Models\VerificationBatchItem;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Phase 4: bulk verification. The items collection is the work queue: create() stages every row, step() claims and
 * checks pending rows a few at a time (safe to call from several workers or browser tabs), and the batch finishes itself
 * when nothing is pending. Each token goes through the same TokenVerifier::check as a single verification, so every
 * check is gated, logged to token_usage_logs and audited, with the batch id attached.
 */
class BatchVerifier
{
    public const CHUNK = 10;
    private const STALE_MINUTES = 2;

    public function __construct(private TokenVerifier $verifier) {}

    /**
     * @param array<int,array{token:string,name?:?string,cv_ref?:?string}> $rows in source order
     * @param array $criteria normalised by TokenVerifier::criteria()
     */
    public function create(User $user, array $criteria, array $rows): VerificationBatch
    {
        $max = config('academiq.bulk_max_tokens');
        if (! $rows) {
            throw ValidationException::withMessages(['tokens' => 'Add at least one token (file, pasted list or QR scans).']);
        }
        if (count($rows) > $max) {
            throw ValidationException::withMessages(['tokens' => 'Too many tokens ('.count($rows)." given, max $max per batch). Split the list."]);
        }
        if (! $criteria) {
            throw ValidationException::withMessages(['criteria' => 'Choose at least one criterion.']);
        }

        $batchId = 'VB-'.Str::upper(Str::random(10));
        $seen = [];
        $items = [];
        foreach (array_values($rows) as $i => $r) {
            $id = TokenVerifier::extract((string) $r['token']);
            $status = ! $id ? 'malformed' : (isset($seen[$id]) ? 'duplicate' : 'pending');
            if ($id) {
                $seen[$id] = true;
            }
            $items[] = ['batch_id' => $batchId, 'row' => $i + 1, 'token_id' => $id, 'input' => $id ? null : Str::limit(preg_replace('/[^\x20-\x7E]/', '', (string) $r['token']), 40, ''),
                'candidate_name' => Str::limit((string) ($r['name'] ?? ''), 100, ''), 'cv_ref' => Str::limit((string) ($r['cv_ref'] ?? ''), 100, ''),
                'status' => $status, 'overall' => $status === 'pending' ? null : ($status === 'duplicate' ? 'DUPLICATE' : 'INVALID'),
                'reason' => ['malformed' => 'malformed_token', 'duplicate' => 'duplicate_token'][$status] ?? null, 'results' => [], 'claimed_at' => null];
        }

        $name = EmployerProfile::where('user_id', (string) $user->getKey())->value('company_name') ?: $user->name;
        $batch = DB::connection('mongodb')->transaction(function () use ($user, $name, $criteria, $items, $batchId) {
            $b = VerificationBatch::create(['batch_id' => $batchId, 'verifier_id' => (string) $user->getKey(), 'verifier_name' => $name, 'criteria' => $criteria,
                'total' => count($items), 'status' => 'queued', 'finished_at' => null]);
            foreach ($items as $it) {
                VerificationBatchItem::create($it);
            }

            return $b;
        });
        AuditLogger::log('bulk.created', 'verification_batch', $batchId, ['total' => count($items), 'duplicates' => count(array_filter($items, fn ($i) => $i['status'] === 'duplicate')),
            'malformed' => count(array_filter($items, fn ($i) => $i['status'] === 'malformed')), 'criteria' => array_column($criteria, 'key')], $user);
        $this->finishIfDone($batch);

        return $batch->refresh();
    }

    /** Process up to $n pending rows. Idempotent and concurrency-safe. @return array progress() */
    public function step(VerificationBatch $b, int $n = self::CHUNK): array
    {
        if ($b->status === 'done') {
            return $this->progress($b);
        }
        VerificationBatch::where('batch_id', $b->batch_id)->where('status', 'queued')->update(['status' => 'running']);
        // A worker that died mid-row leaves it 'processing'; give such rows back to the queue.
        VerificationBatchItem::where('batch_id', $b->batch_id)->where('status', 'processing')->where('claimed_at', '<', now()->subMinutes(self::STALE_MINUTES))->update(['status' => 'pending']);

        $user = User::find($b->verifier_id);
        foreach (VerificationBatchItem::where('batch_id', $b->batch_id)->where('status', 'pending')->orderBy('row')->limit($n)->get() as $item) {
            if (VerificationBatchItem::where('_id', $item->getKey())->where('status', 'pending')->update(['status' => 'processing', 'claimed_at' => now()]) !== 1) {
                continue; // someone else took it
            }
            try {
                $r = $this->verifier->check($user, $item->token_id, $b->criteria, ['batch_id' => $b->batch_id]);
                $item->update(['status' => 'done', 'overall' => $r['overall'], 'reason' => $r['reason'], 'results' => $r['results'], 'usage_log_id' => $r['check_id']]);
            } catch (Throwable $e) {
                report($e);
                $item->update(['status' => 'done', 'overall' => 'INVALID', 'reason' => 'system_error', 'results' => []]);
            }
        }
        $this->finishIfDone($b);

        return $this->progress($b->refresh());
    }

    /** @return array{status:string,total:int,processed:int,counts:array<string,int>} */
    public function progress(VerificationBatch $b): array
    {
        $items = VerificationBatchItem::where('batch_id', $b->batch_id)->get(['status', 'overall']);
        $counts = ['PASS' => 0, 'FAIL' => 0, 'NOT PROVEN' => 0, 'INVALID' => 0, 'DUPLICATE' => 0];
        foreach ($items as $i) {
            if (isset($counts[$i->overall])) {
                $counts[$i->overall]++;
            }
        }

        return ['status' => $b->status, 'total' => (int) $b->total, 'processed' => $items->whereNotIn('status', ['pending', 'processing'])->count(), 'counts' => $counts];
    }

    public static function reasonText(?string $reason): string
    {
        return match ($reason) {
            'malformed_token' => 'Not a valid token ID.', 'duplicate_token' => 'Duplicate of an earlier row; not checked again.', 'system_error' => 'The check could not be completed.',
            default => TokenVerifier::reasonText($reason),
        };
    }

    private function finishIfDone(VerificationBatch $b): void
    {
        if (VerificationBatchItem::where('batch_id', $b->batch_id)->whereIn('status', ['pending', 'processing'])->exists()) {
            return;
        }
        // Atomic: exactly one caller completes the batch (and writes the audit entry).
        if (VerificationBatch::where('batch_id', $b->batch_id)->where('status', '!=', 'done')->update(['status' => 'done', 'finished_at' => now()]) === 1) {
            AuditLogger::log('bulk.completed', 'verification_batch', $b->batch_id, ['counts' => $this->progress($b->refresh())['counts']], User::find($b->verifier_id));
        }
    }
}
