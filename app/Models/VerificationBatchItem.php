<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * D2: one token in a bulk run, doubling as the work queue.
 * status: pending | processing | done | duplicate | malformed. Outcomes only, no credential values.
 */
class VerificationBatchItem extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_batch_items';
    protected $guarded = [];
    protected $casts = ['claimed_at' => 'datetime'];
}
