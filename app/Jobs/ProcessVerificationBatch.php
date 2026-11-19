<?php

namespace App\Jobs;

use App\Models\VerificationBatch;
use App\Services\Verification\BatchVerifier;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Runs a bulk verification to completion on a queue worker. Only dispatched when QUEUE_CONNECTION is not "sync". */
class ProcessVerificationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public string $batchId) {}

    public function handle(BatchVerifier $svc): void
    {
        $b = VerificationBatch::where('batch_id', $this->batchId)->first();
        while ($b && $svc->step($b)['status'] !== 'done') {
            $b = $b->refresh();
        }
    }
}
