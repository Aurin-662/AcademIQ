<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VerificationRequest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_requests';
    protected $guarded = [];
}
