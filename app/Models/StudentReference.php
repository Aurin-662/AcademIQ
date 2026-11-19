<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class StudentReference extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'student_references';
    protected $guarded = [];
}
