<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class EmploymentVerification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'employment_verifications';
    protected $guarded = [];
}
