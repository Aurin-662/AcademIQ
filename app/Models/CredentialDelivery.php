<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CredentialDelivery extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'credential_deliveries';
    protected $guarded = [];
}
