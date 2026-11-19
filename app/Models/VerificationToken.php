<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * D2: a shareable claim-set proof. Holds the Groth16 proof + public signals (no private inputs, ever).
 * status: pending (prepared, awaiting proof) | verifying | failed | active | revoked. Expiry is derived from expires_at.
 */
class VerificationToken extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verification_tokens';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'consented_at' => 'datetime'];

    /** One line per claim, no values. Used by the token page, the CV badge and reports. */
    public function summaryLines(): array
    {
        return collect($this->claims)->map(fn ($c) => match ($c['claim']) {
            'cgpa' => $c['mode'] === 'reveal' ? 'CGPA (exact value shared)' : 'CGPA thresholds (value hidden)',
            'passing_year' => $c['lo'] === $c['hi'] ? 'Passing year '.$c['lo'] : 'Passing year '.$c['lo'].' to '.$c['hi'],
            default => ucfirst($c['claim']).': '.$c['label'],
        })->all();
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->expires_at && $this->expires_at->isFuture();
    }
}
