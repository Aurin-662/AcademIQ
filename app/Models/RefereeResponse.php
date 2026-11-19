<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class RefereeResponse extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'referee_responses';
    protected $guarded = [];
}
