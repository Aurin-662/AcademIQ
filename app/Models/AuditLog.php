<?php

namespace App\Models;

use LogicException;
use MongoDB\Laravel\Eloquent\Model;

/** Append-only: normal application code cannot update or delete rows. */
class AuditLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'audit_logs';
    public $timestamps = false;
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }
}
