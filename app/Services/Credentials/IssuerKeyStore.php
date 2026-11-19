<?php

namespace App\Services\Credentials;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

/** Private issuer keys: encrypted with APP_KEY, stored on disk outside MongoDB, never sent to browsers. */
class IssuerKeyStore
{
    public function __construct(private NodeCrypto $node) {}

    /** @return array{key_id:string, pub:array{0:string,1:string}} */
    public function generate(): array
    {
        $k = $this->node->call(['op' => 'keygen']);
        $keyId = 'ik_'.Str::lower(Str::random(16));
        $dir = config('academiq.issuer_key_path');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($this->file($keyId), Crypt::encryptString($k['priv_hex']));
        @chmod($this->file($keyId), 0600);

        return ['key_id' => $keyId, 'pub' => $k['pub']];
    }

    public function sign(string $keyId, string $commitment): array
    {
        return $this->node->call(['op' => 'sign', 'priv_hex' => $this->priv($keyId), 'msg' => $commitment]);
    }

    /** @param string[] $commitments @return array[] signatures in order */
    public function signMany(string $keyId, array $commitments): array
    {
        $priv = $this->priv($keyId);

        return $this->node->many(array_map(fn ($m) => ['op' => 'sign', 'priv_hex' => $priv, 'msg' => $m], $commitments));
    }

    private function priv(string $keyId): string
    {
        $f = $this->file($keyId);
        if (! is_file($f)) {
            throw new RuntimeException('Issuer signing key unavailable.');
        }

        return Crypt::decryptString(file_get_contents($f));
    }

    private function file(string $keyId): string
    {
        abort_unless(preg_match('/^ik_[a-z0-9]{16}$/', $keyId), 500);

        return rtrim(config('academiq.issuer_key_path'), '/\\').DIRECTORY_SEPARATOR.$keyId.'.key';
    }
}
