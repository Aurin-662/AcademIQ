<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BulkVerificationRequest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'bulk_verification_requests';

    public $timestamps = false;

    protected $fillable = [
        'requested_by',
        'file_name',
        'total_records',
        'processed',
        'status',
        'result_summary',
        'results',
        'created_at',
    ];

    protected $casts = [
        'total_records'  => 'integer',
        'processed'      => 'integer',
        'result_summary' => 'array',
        'results'        => 'array',
        'created_at'     => 'datetime',
    ];
}
