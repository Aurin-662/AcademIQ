<?php

namespace App\Console\Commands;

use App\Models\VerificationKey;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class ZkpRegister extends Command
{
    protected $signature = 'zkp:register {circuit=credential} {version=1} {--retire-others : Retire other active versions of this circuit}';
    protected $description = 'Register a Groth16 verification key (from zkp/verification-keys) in D1.';

    public function handle(): int
    {
        $file = base_path("zkp/verification-keys/{$this->argument('circuit')}_v{$this->argument('version')}.vkey.json");
        if (! is_file($file)) {
            $this->error("Missing $file. Run zkp/build.sh first.");
            return 1;
        }
        $vkey = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $v = (int) $this->argument('version');
        if ($this->option('retire-others')) {
            VerificationKey::where('circuit_id', $this->argument('circuit'))->where('circuit_version', '!=', $v)->update(['status' => 'retired']);
        }
        VerificationKey::updateOrCreate(
            ['circuit_id' => $this->argument('circuit'), 'circuit_version' => $v],
            ['proof_system' => 'groth16', 'verification_key' => $vkey, 'sha256' => hash('sha256', file_get_contents($file)), 'status' => 'active', 'activated_at' => now()],
        );
        AuditLogger::log('admin.verification_key_registered', 'verification_key', $this->argument('circuit').'_v'.$v);
        $this->info('Registered.');

        return 0;
    }
}
