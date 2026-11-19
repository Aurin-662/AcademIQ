<?php

namespace App\Services\Credentials;

use App\Models\AuthorizedIssuer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

class IssuerService
{
    public function __construct(private IssuerKeyStore $keys) {}

    public function authorize(User $user, string $name, string $designation): AuthorizedIssuer
    {
        $k = $this->keys->generate();
        $issuer = AuthorizedIssuer::create([
            'issuer_id' => 'ISS-'.Str::upper(Str::random(8)), 'user_id' => (string) $user->getKey(), 'name' => $name, 'designation' => $designation,
            'authorization_status' => 'active', 'current_key_id' => $k['key_id'],
            'keys' => [['key_id' => $k['key_id'], 'ax' => $k['pub'][0], 'ay' => $k['pub'][1], 'status' => 'active', 'created_at' => now()->toIso8601String()]],
        ]);
        AuditLogger::log('admin.issuer_authorized', 'authorized_issuer', $issuer->issuer_id, ['key_id' => $k['key_id']]);

        return $issuer;
    }

    /** New key becomes current; old key is retired (credentials it signed stay verifiable until re-issued or revoked). */
    public function rotate(AuthorizedIssuer $issuer): AuthorizedIssuer
    {
        $k = $this->keys->generate();
        $keys = collect($issuer->keys)->map(fn ($x) => $x['key_id'] === $issuer->current_key_id ? [...$x, 'status' => 'retired'] : $x)->all();
        $keys[] = ['key_id' => $k['key_id'], 'ax' => $k['pub'][0], 'ay' => $k['pub'][1], 'status' => 'active', 'created_at' => now()->toIso8601String()];
        $issuer->update(['keys' => $keys, 'current_key_id' => $k['key_id']]);
        AuditLogger::log('admin.issuer_key_rotated', 'authorized_issuer', $issuer->issuer_id, ['new_key_id' => $k['key_id']]);

        return $issuer;
    }
}
