<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Institution extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'institutions';

    protected $fillable = [
        'code',
        'name',
        'verified',
        'created_at',
    ];

    protected $casts = [
        'verified'   => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
