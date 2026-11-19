<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AuthorizedIssuer extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'authorized_issuers';
    protected $guarded = [];
}
