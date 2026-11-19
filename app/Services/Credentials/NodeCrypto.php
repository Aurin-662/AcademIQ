<?php

namespace App\Services\Credentials;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Bridge to zkp/tools/crypto.mjs (Poseidon, EdDSA-Poseidon, ECIES seal). Input goes via stdin, never argv or logs. */
class NodeCrypto
{
    public function call(array $cmd): array
    {
        $p = new Process([config('academiq.node_bin'), base_path('zkp/tools/crypto.mjs')], base_path(), null, json_encode($cmd), 30);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException('Crypto helper failed (exit '.$p->getExitCode().').'); // stderr deliberately not echoed
        }

        return json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Runs many ops in one Node process (spawn cost dominates). @return array[] results in order */
    public function many(array $cmds): array
    {
        return $cmds ? $this->call(['op' => 'batch', 'cmds' => array_values($cmds)])['results'] : [];
    }

    public function commit(array $fields): string { return $this->call(['op' => 'commit', 'fields' => array_map('strval', $fields)])['commitment']; }
    public function seal(string $pubB64, string $plaintext): array { return $this->call(['op' => 'seal', 'recipient_pub_b64' => $pubB64, 'plaintext' => $plaintext]); }
}
