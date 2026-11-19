<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Credential extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'credentials';
    protected $guarded = [];
}
