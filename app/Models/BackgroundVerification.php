<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BackgroundVerification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'background_verifications';
    protected $guarded = [];
}
