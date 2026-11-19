<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProofSubmission extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'proof_submissions';
    protected $guarded = [];
}
