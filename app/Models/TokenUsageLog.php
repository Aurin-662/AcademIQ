<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/** D2: one row per verifier check of a token (written from Phase 3). Outcomes only. */
class TokenUsageLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'token_usage_logs';
    protected $guarded = [];
}
