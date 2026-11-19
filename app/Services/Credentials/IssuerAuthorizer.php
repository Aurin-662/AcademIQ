<?php

namespace App\Services\Credentials;

use App\Models\AuthorizedIssuer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/** Process 1.2: validate issuer authorization. Every outcome is audited. */
class IssuerAuthorizer
{
    public function authorize(User $actor, string $action): AuthorizedIssuer
    {
        $issuer = AuthorizedIssuer::where('user_id', (string) $actor->getKey())->first();
        $ok = $actor->is_active && $actor->hasRole('admin', 'issuer') && $issuer && $issuer->authorization_status === 'active';

        AuditLogger::log($ok ? 'issuer.authorization_accepted' : 'issuer.authorization_rejected', 'authorized_issuer', $issuer?->issuer_id, ['attempted' => $action], $actor);
        if (! $ok) {
            throw new AuthorizationException('You are not an authorized issuer.');
        }

        return $issuer;
    }
}
