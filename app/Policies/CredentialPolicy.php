<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\StudentProfile;
use App\Models\User;

class CredentialPolicy
{
    /** A student may see only their own credential metadata. Issuers/admins manage via role routes. */
    public function view(User $user, Credential $credential): bool
    {
        if ($user->hasRole('admin', 'issuer')) {
            return true;
        }
        $ref = StudentProfile::where('user_id', (string) $user->getKey())->value('student_reference');

        return $user->hasRole('student') && $ref !== null && $ref === $credential->student_reference;
    }
}
