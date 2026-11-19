<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /** Never pass secrets, credential contents, salts, proofs or witnesses in $meta. */
    public static function log(string $action, ?string $entityType = null, ?string $entityRef = null, array $meta = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();
        $req = request();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->role,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_reference' => $entityRef,
            'timestamp' => now(),
            'ip' => $req?->ip(),
            'user_agent' => substr((string) $req?->userAgent(), 0, 255),
            'event_metadata' => $meta,
        ]);
    }
}
