<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProofResult extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'proof_results';
    protected $guarded = [];
}
