<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VerificationReport extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_reports';
    protected $guarded = [];
}
