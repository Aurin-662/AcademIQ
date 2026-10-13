<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VerificationLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_logs';

    public $timestamps = false;

    protected $fillable = [
        'certificate_id',
        'verified_by',
        'verification_type',
        'result',
        'ip_address',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}
