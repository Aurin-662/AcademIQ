<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CredentialVersion extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'credential_versions';
    protected $guarded = [];
}
