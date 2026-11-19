<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VerificationKey extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_keys';
    protected $guarded = [];
}
