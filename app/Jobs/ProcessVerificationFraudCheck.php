<?php

namespace App\Jobs;

use App\Models\FraudFlag;
use App\Models\VerificationLog;
use App\Services\FraudService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessVerificationFraudCheck implements ShouldQueue
{
    use Queueable;

    public string $documentId;
    public string $verifiedBy;
    public string $ipAddress;
    public string $result;

    /**
     * Create a new job instance.
     */
    public function __construct(string $documentId, string $verifiedBy, string $ipAddress, string $result)
    {
        $this->documentId = $documentId;
        $this->verifiedBy = $verifiedBy;
        $this->ipAddress  = $ipAddress;
        $this->result     = $result;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. If result is tampered, create hash_mismatch flag if not already pending
        if ($this->result === 'tampered') {
            $existingFlag = FraudFlag::where('certificate_id', $this->documentId)
                ->where('flag_type', 'hash_mismatch')
                ->where('status', 'pending_review')
                ->first();

            if (!$existingFlag) {
                FraudFlag::create([
                    'certificate_id' => $this->documentId,
                    'flag_type'      => 'hash_mismatch',
                    'score'          => 1.0,
                    'details'        => [
                        'reason'      => 'Cryptographic HMAC mismatch detected on verification attempt',
                        'verified_by' => $this->verifiedBy,
                        'ip_address'  => $this->ipAddress,
                    ],
                    'status'         => 'pending_review',
                    'detected_at'    => now(),
                    'reviewed_by'    => null,
                    'reviewed_at'    => null,
                ]);
                Log::warning("[Fraud] Hash mismatch flag created for document: {$this->documentId}");
            }
        }

        // 2. Anomaly scoring for requester over last 10 minutes
        $tenMinutesAgo = new \MongoDB\BSON\UTCDateTime(Carbon::now()->subMinutes(10)->getTimestamp() * 1000);
        $logs = VerificationLog::where('ip_address', $this->ipAddress)
            ->where('timestamp', '>=', $tenMinutesAgo)
            ->get();

        if ($logs->isEmpty() && $this->verifiedBy !== $this->ipAddress) {
            $logs = VerificationLog::where('verified_by', $this->verifiedBy)
                ->where('timestamp', '>=', $tenMinutesAgo)
                ->get();
        }

        $requestCount = $logs->count();
        Log::info("ProcessVerificationFraudCheck evaluated for {$this->ipAddress}: count={$requestCount}");
        if ($requestCount < 5) {
            // Not enough traffic to trigger anomaly check
            return;
        }

        $distinctIds = $logs->pluck('certificate_id')->unique()->count();
        $notFoundCount = $logs->where('result', 'not_found')->count();
        $tamperedCount = $logs->where('result', 'tampered')->count();

        $notFoundRatio = $requestCount > 0 ? ($notFoundCount / $requestCount) : 0.0;
        $tamperedRatio = $requestCount > 0 ? ($tamperedCount / $requestCount) : 0.0;
        $hourOfDay = (int) now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('G');

        $features = [
            'request_count_10m' => $requestCount,
            'distinct_ids_10m'  => $distinctIds,
            'not_found_ratio'   => round($notFoundRatio, 4),
            'tampered_ratio'    => round($tamperedRatio, 4),
            'hour_of_day'       => $hourOfDay,
        ];

        // Call FraudService for anomaly scoring
        if (class_exists(FraudService::class)) {
            try {
                $fraudService = app(FraudService::class);
                $anomalyResult = $fraudService->scoreAnomaly($features);

                if (!empty($anomalyResult['is_anomaly'])) {
                    // Check if a recent anomaly flag for this IP/requester was already logged in the last 10m
                    $recentAnomaly = FraudFlag::where('flag_type', 'anomalous_verification_pattern')
                        ->where('detected_at', '>=', $tenMinutesAgo)
                        ->get()
                        ->first(fn($f) => isset($f->details['ip_address']) && $f->details['ip_address'] === $this->ipAddress);

                    if (!$recentAnomaly) {
                        FraudFlag::create([
                            'certificate_id' => $this->documentId,
                            'flag_type'      => 'anomalous_verification_pattern',
                            'score'          => (float) ($anomalyResult['score'] ?? 0.85),
                            'details'        => array_merge($features, [
                                'verified_by' => $this->verifiedBy,
                                'ip_address'  => $this->ipAddress,
                                'engine'      => $anomalyResult['engine'] ?? 'isolation_forest',
                            ]),
                            'status'         => 'pending_review',
                            'detected_at'    => now(),
                            'reviewed_by'    => null,
                            'reviewed_at'    => null,
                        ]);
                        Log::alert("[Fraud] Anomalous verification pattern flag created for IP: {$this->ipAddress}");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Anomaly scoring job error: ' . $e->getMessage());
            }
        }
    }
}
