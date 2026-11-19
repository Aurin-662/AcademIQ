<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class StudentProfile extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'student_profiles';
    protected $fillable = ['user_id', 'student_reference', 'department'];
}
