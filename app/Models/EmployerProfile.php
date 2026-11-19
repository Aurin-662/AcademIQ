<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class EmployerProfile extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'employer_profiles';
    protected $fillable = ['user_id', 'company_name', 'website', 'contact_phone'];
}
