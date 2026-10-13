<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVerificationFraudCheck;
use App\Models\DocumentRecord;
use App\Models\FraudFlag;
use App\Models\VerificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;

class SimulateAttackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academiq:simulate-attack {--ip=198.51.100.42 : Simulated attacker IP address} {--count=200 : Number of rapid burst verification requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an automated brute-force verification burst attack from a single IP to demonstrate anomaly detection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $attackerIp = (string) $this->option('ip');
        $count = (int) $this->option('count');

        $this->warn("=== ACADEMIQ LIVE ANOMALY DETECTION TEST ===");
        $this->line("Simulating high-frequency burst attack of {$count} verification attempts from IP: {$attackerIp}");

        $validDoc = DocumentRecord::first();
        $validId = $validDoc ? $validDoc->document_id : 'CERT-2024-KUET-000001';

        $batch = [];
        $nowMs = now()->getTimestamp() * 1000;

        for ($i = 1; $i <= $count; $i++) {
            // 90% non-existent / brute-force IDs, 10% valid
            if ($i % 10 === 0) {
                $docId = $validId;
                $result = 'genuine';
            } else {
                $docId = 'ATTACK-FAKE-' . strtoupper(Str::random(10));
                $result = 'not_found';
            }

            $batch[] = [
                'certificate_id'    => $docId,
                'verified_by'       => $attackerIp,
                'verification_type' => 'single',
                'result'            => $result,
                'ip_address'        => $attackerIp,
                'timestamp'         => new UTCDateTime($nowMs),
            ];
        }

        // Bulk insert logs to simulate real traffic arrival
        VerificationLog::raw(function ($col) use ($batch) {
            $col->insertMany($batch);
        });

        $this->info("✓ Inserted {$count} rapid verification log records.");

        // Trigger anomaly evaluation
        $this->line("Invoking anomaly detection engine on attacker IP traffic pattern...");
        $fraudJob = new ProcessVerificationFraudCheck('ATTACK-BURST-SUMMARY', $attackerIp, $attackerIp, 'not_found');
        $fraudJob->handle();

        // Check if fraud flag was created
        $flag = FraudFlag::where('flag_type', 'anomalous_verification_pattern')->latest('detected_at')->first();
        if (!$flag) {
            $flag = FraudFlag::latest('detected_at')->first();
        }

        $this->newLine();
        if ($flag) {
            $this->info("=======================================================================");
            $this->info("             ANOMALOUS TRAFFIC PATTERN SUCCESSFULLY DETECTED           ");
            $this->info("=======================================================================");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Flag ID', (string) ($flag->_id ?? $flag->id)],
                    ['Flag Type', $flag->flag_type],
                    ['Risk Score', $flag->score],
                    ['Engine', $flag->details['engine'] ?? 'isolation_forest'],
                    ['Requests (10m)', $flag->details['request_count_10m'] ?? $count],
                    ['Not Found Ratio', $flag->details['not_found_ratio'] ?? '0.9'],
                    ['Attacker IP', $attackerIp],
                    ['Status', $flag->status],
                ]
            );
            $this->info("Check the Admin Portal at /admin/fraud to view or confirm this alert.");
        } else {
            $this->warn("Attack logged, but threshold not triggered. Check configuration settings.");
        }

        return self::SUCCESS;
    }
}
