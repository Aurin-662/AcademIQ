<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/** D1: one record per bulk issuance. Counts, digest and per-row errors (student id + messages only, never claim values). */
class IssuanceBatch extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'issuance_batches';
    protected $guarded = [];
}
