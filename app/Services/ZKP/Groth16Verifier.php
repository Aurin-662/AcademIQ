<?php

namespace App\Services\ZKP;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Cryptographic verification via SnarkJS (zkp/tools/verify.mjs). Proof material is passed on stdin and never stored or logged. */
class Groth16Verifier
{
    public function verify(array $vkey, array $proof, array $publicSignals): bool
    {
        $p = new Process([config('academiq.node_bin'), base_path('zkp/tools/verify.mjs')], base_path(), null, json_encode(['vkey' => $vkey, 'proof' => $proof, 'publicSignals' => $publicSignals]), 60);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException('Verifier process failed.');
        }

        return (bool) (json_decode($p->getOutput(), true)['valid'] ?? false);
    }
}
