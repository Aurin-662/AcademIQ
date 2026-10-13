<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class FraudFlag extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'fraud_flags';

    public $timestamps = false;

    protected $fillable = [
        'certificate_id',
        'flag_type',
        'score',
        'details',
        'status',
        'detected_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'score'       => 'float',
        'details'     => 'array',
        'detected_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}
