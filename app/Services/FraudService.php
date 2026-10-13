<?php

namespace App\Services;

use App\Models\DocumentRecord;
use App\Models\FraudFlag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FraudService
{
    protected string $serviceUrl;
    protected float $fuzzyThreshold;

    public function __construct()
    {
        $this->serviceUrl     = config('academiq.fraud_service_url', 'http://127.0.0.1:8001');
        $this->fuzzyThreshold = (float) config('academiq.fuzzy_threshold', 85.0);
    }

    /**
     * Check a new certificate candidate for duplicate rolls and near-duplicate names
     *
     * @param array $candidateData [document_id, recipient_name, roll_no, issuer_id, passing_year, department]
     * @return array List of created fraud flags
     */
    public function checkNewCertificate(array $candidateData): array
    {
        $issuerId = $candidateData['issuer_id'] ?? '';
        $rollNo = $candidateData['roll_no'] ?? '';
        $passingYear = (int) ($candidateData['passing_year'] ?? 0);

        // Gather candidate neighbours: same issuer and same roll number, or same issuer + passing year (capped at 2000)
        $neighbours = DocumentRecord::where('issuer_id', $issuerId)
            ->where(function ($q) use ($rollNo, $passingYear) {
                if ($rollNo !== '') {
                    $q->where('roll_no', $rollNo);
                }
                if ($passingYear > 0) {
                    $q->orWhere('passing_year', $passingYear);
                }
            })
            ->where('document_id', '!=', $candidateData['document_id'])
            ->limit(2000)
            ->get(['document_id', 'recipient_name', 'roll_no']);

        if ($neighbours->isEmpty()) {
            return [];
        }

        $existing = $neighbours->map(function ($doc) {
            return [
                'document_id' => $doc->document_id,
                'name'        => $doc->recipient_name,
                'roll_no'     => $doc->roll_no,
            ];
        })->values()->all();

        $matches = [];

        // Attempt microservice call
        try {
            $response = Http::timeout(2.0)->post($this->serviceUrl . '/fuzzy-check', [
                'candidate' => [
                    'name'         => $candidateData['recipient_name'],
                    'roll_no'      => $candidateData['roll_no'],
                    'issuer_id'    => $candidateData['issuer_id'],
                    'passing_year' => $candidateData['passing_year'],
                    'department'   => $candidateData['department'],
                ],
                'existing'  => $existing,
                'threshold' => $this->fuzzyThreshold,
            ]);

            if ($response->successful()) {
                $matches = $response->json();
            } else {
                Log::warning("[FraudService] Fuzzy service returned HTTP " . $response->status() . ", falling back to rule-based engine.");
                $matches = $this->fallbackFuzzyCheck($candidateData, $existing);
            }
        } catch (\Throwable $e) {
            Log::warning("[FraudService] Microservice unavailable ({$e->getMessage()}), using rule-based fallback.");
            $matches = $this->fallbackFuzzyCheck($candidateData, $existing);
        }

        $createdFlags = [];
        foreach ($matches as $match) {
            $flag = FraudFlag::create([
                'certificate_id' => $candidateData['document_id'],
                'flag_type'      => $match['reason'],
                'score'          => (float) $match['score'],
                'details'        => [
                    'conflicting_document_id' => $match['document_id'],
                    'candidate_name'          => $candidateData['recipient_name'],
                    'candidate_roll'          => $candidateData['roll_no'],
                    'issuer_id'               => $candidateData['issuer_id'],
                    'reason'                  => $match['reason'],
                ],
                'status'         => 'pending_review',
                'detected_at'    => now(),
                'reviewed_by'    => null,
                'reviewed_at'    => null,
            ]);
            $createdFlags[] = $flag;
        }

        return $createdFlags;
    }

    /**
     * Score requester anomaly pattern using IsolationForest or fallback
     *
     * @param array $features [request_count_10m, distinct_ids_10m, not_found_ratio, tampered_ratio, hour_of_day]
     * @return array [score, is_anomaly, engine]
     */
    public function scoreAnomaly(array $features): array
    {
        try {
            $response = Http::timeout(2.0)->post($this->serviceUrl . '/anomaly-score', $features);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("[FraudService] Anomaly service returned status " . $response->status() . ", using fallback.");
            return $this->fallbackAnomalyScore($features);
        } catch (\Throwable $e) {
            Log::warning("[FraudService] Microservice unreachable for anomaly scoring ({$e->getMessage()}), using fallback.");
            return $this->fallbackAnomalyScore($features);
        }
    }

    /**
     * Fallback rule-based duplicate roll and string similarity check
     */
    protected function fallbackFuzzyCheck(array $candidate, array $existing): array
    {
        $results = [];
        $candName = strtolower(trim($candidate['recipient_name']));
        $candRoll = strval($candidate['roll_no'] ?? '');

        foreach ($existing as $rec) {
            $recName = strtolower(trim($rec['name']));
            $recRoll = strval($rec['roll_no'] ?? '');

            // Rule 1: Duplicate Roll with different recipient name
            if ($candRoll !== '' && $recRoll !== '' && $candRoll === $recRoll && $candName !== $recName) {
                $results[] = [
                    'document_id' => $rec['document_id'],
                    'score'       => 1.0,
                    'reason'      => 'duplicate_roll',
                ];
                continue;
            }

            // Rule 2: Near duplicate name using similar_text
            similar_text($candName, $recName, $percent);
            if ($percent >= $this->fuzzyThreshold && $percent < 100.0) {
                $results[] = [
                    'document_id' => $rec['document_id'],
                    'score'       => round($percent, 2),
                    'reason'      => 'near_duplicate_name',
                ];
            }
        }

        return $results;
    }

    /**
     * Fallback rule-based anomaly scoring
     */
    protected function fallbackAnomalyScore(array $features): array
    {
        $maxRequests = config('academiq.anomaly_requests_10m', 30);
        $maxNotFound = config('academiq.anomaly_not_found_ratio', 0.5);

        $isAnomaly = ($features['request_count_10m'] >= $maxRequests || $features['not_found_ratio'] > $maxNotFound);

        return [
            'score'      => $isAnomaly ? 0.95 : 0.15,
            'is_anomaly' => $isAnomaly,
            'engine'     => 'rule_based_fallback',
        ];
    }
}
