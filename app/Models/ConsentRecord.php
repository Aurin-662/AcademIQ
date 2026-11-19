<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'consent_records';
    protected $guarded = [];
}
