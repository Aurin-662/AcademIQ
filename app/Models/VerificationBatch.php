<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/** D2: one bulk verification run. Criteria (labels only), counts and status; per-token outcomes live in verification_batch_items. */
class VerificationBatch extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_batches';
    protected $guarded = [];
    protected $casts = ['finished_at' => 'datetime'];
}
