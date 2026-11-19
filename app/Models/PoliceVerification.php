<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PoliceVerification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'police_verifications';
    protected $guarded = [];
}
