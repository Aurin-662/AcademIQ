<?php

namespace App\Services;

use App\Models\FraudFlag;
use App\Models\VerificationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\UTCDateTime;

class AnalyticsService
{
    /**
     * Get verifications per day over last 30 days
     */
    public function getVerificationsPerDay(?string $institutionCode = null): array
    {
        $cacheKey = 'analytics_per_day_' . ($institutionCode ?: 'all');

        return Cache::remember($cacheKey, 60, function () use ($institutionCode) {
            $t0 = microtime(true);
            $thirtyDaysAgo = new UTCDateTime(Carbon::now()->subDays(30)->startOfDay()->getTimestamp() * 1000);

            $pipeline = [
                ['$match' => ['timestamp' => ['$gte' => $thirtyDaysAgo]]],
            ];

            if ($institutionCode) {
                $pipeline[] = [
                    '$lookup' => [
                        'from'         => 'documents',
                        'localField'   => 'certificate_id',
                        'foreignField' => 'document_id',
                        'as'           => 'doc',
                    ]
                ];
                $pipeline[] = ['$match' => ['doc.issuer_id' => $institutionCode]];
            }

            $pipeline[] = [
                '$group' => [
                    '_id'   => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$timestamp']],
                    'count' => ['$sum' => 1],
                ]
            ];
            $pipeline[] = ['$sort' => ['_id' => 1]];

            $cursor = VerificationLog::raw(fn($col) => $col->aggregate($pipeline));

            $labels = [];
            $values = [];
            foreach ($cursor as $item) {
                $labels[] = (string) $item['_id'];
                $values[] = (int) $item['count'];
            }

            $queryTimeMs = round((microtime(true) - $t0) * 1000, 2);

            return [
                'labels'        => $labels,
                'data'          => $values,
                'query_time_ms' => $queryTimeMs,
            ];
        });
    }

    /**
     * Get verification results breakdown (genuine, tampered, not_found, revoked)
     */
    public function getResultBreakdown(?string $institutionCode = null): array
    {
        $cacheKey = 'analytics_breakdown_' . ($institutionCode ?: 'all');

        return Cache::remember($cacheKey, 60, function () use ($institutionCode) {
            $t0 = microtime(true);

            $pipeline = [];

            if ($institutionCode) {
                $pipeline[] = [
                    '$lookup' => [
                        'from'         => 'documents',
                        'localField'   => 'certificate_id',
                        'foreignField' => 'document_id',
                        'as'           => 'doc',
                    ]
                ];
                $pipeline[] = ['$match' => ['doc.issuer_id' => $institutionCode]];
            }

            $pipeline[] = [
                '$group' => [
                    '_id'   => '$result',
                    'count' => ['$sum' => 1],
                ]
            ];
            $pipeline[] = ['$sort' => ['count' => -1]];

            $cursor = VerificationLog::raw(fn($col) => $col->aggregate($pipeline));

            $labels = [];
            $values = [];
            foreach ($cursor as $item) {
                $labels[] = ucfirst((string) $item['_id']);
                $values[] = (int) $item['count'];
            }

            $queryTimeMs = round((microtime(true) - $t0) * 1000, 2);

            return [
                'labels'        => $labels,
                'data'          => $values,
                'query_time_ms' => $queryTimeMs,
            ];
        });
    }

    /**
     * Get top 10 institutions by verification volume
     */
    public function getTopInstitutions(int $limit = 10): array
    {
        $cacheKey = 'analytics_top_institutions_' . $limit;

        return Cache::remember($cacheKey, 60, function () use ($limit) {
            $t0 = microtime(true);

            $pipeline = [
                [
                    '$lookup' => [
                        'from'         => 'documents',
                        'localField'   => 'certificate_id',
                        'foreignField' => 'document_id',
                        'as'           => 'doc',
                    ]
                ],
                ['$unwind' => '$doc'],
                [
                    '$group' => [
                        '_id'   => '$doc.issuer_name',
                        'code'  => ['$first' => '$doc.issuer_id'],
                        'count' => ['$sum' => 1],
                    ]
                ],
                ['$sort' => ['count' => -1]],
                ['$limit' => $limit],
            ];

            $cursor = VerificationLog::raw(fn($col) => $col->aggregate($pipeline));

            $labels = [];
            $values = [];
            foreach ($cursor as $item) {
                $labels[] = (string) $item['_id'];
                $values[] = (int) $item['count'];
            }

            $queryTimeMs = round((microtime(true) - $t0) * 1000, 2);

            return [
                'labels'        => $labels,
                'data'          => $values,
                'query_time_ms' => $queryTimeMs,
            ];
        });
    }

    /**
     * Get verifications by hour of day (0 - 23) in Dhaka time (+06:00)
     */
    public function getVerificationsByHour(?string $institutionCode = null): array
    {
        $cacheKey = 'analytics_by_hour_' . ($institutionCode ?: 'all');

        return Cache::remember($cacheKey, 60, function () use ($institutionCode) {
            $t0 = microtime(true);

            $pipeline = [];

            if ($institutionCode) {
                $pipeline[] = [
                    '$lookup' => [
                        'from'         => 'documents',
                        'localField'   => 'certificate_id',
                        'foreignField' => 'document_id',
                        'as'           => 'doc',
                    ]
                ];
                $pipeline[] = ['$match' => ['doc.issuer_id' => $institutionCode]];
            }

            $pipeline[] = [
                '$group' => [
                    '_id'   => ['$hour' => ['date' => '$timestamp', 'timezone' => '+06:00']],
                    'count' => ['$sum' => 1],
                ]
            ];
            $pipeline[] = ['$sort' => ['_id' => 1]];

            $cursor = VerificationLog::raw(fn($col) => $col->aggregate($pipeline));

            $hoursMap = array_fill(0, 24, 0);
            foreach ($cursor as $item) {
                $h = (int) $item['_id'];
                if ($h >= 0 && $h <= 23) {
                    $hoursMap[$h] = (int) $item['count'];
                }
            }

            $labels = array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23));
            $values = array_values($hoursMap);

            $queryTimeMs = round((microtime(true) - $t0) * 1000, 2);

            return [
                'labels'        => $labels,
                'data'          => $values,
                'query_time_ms' => $queryTimeMs,
            ];
        });
    }

    /**
     * Get fraud flags breakdown by flag_type and status
     */
    public function getFraudFlagsByTypeAndStatus(?string $institutionCode = null): array
    {
        $cacheKey = 'analytics_fraud_flags_' . ($institutionCode ?: 'all');

        return Cache::remember($cacheKey, 60, function () use ($institutionCode) {
            $t0 = microtime(true);

            $pipeline = [];

            if ($institutionCode) {
                $pipeline[] = ['$match' => ['details.issuer_id' => $institutionCode]];
            }

            $pipeline[] = [
                '$group' => [
                    '_id'   => '$flag_type',
                    'count' => ['$sum' => 1],
                ]
            ];
            $pipeline[] = ['$sort' => ['count' => -1]];

            $cursor = FraudFlag::raw(fn($col) => $col->aggregate($pipeline));

            $labels = [];
            $values = [];
            foreach ($cursor as $item) {
                $labels[] = ucwords(str_replace('_', ' ', (string) $item['_id']));
                $values[] = (int) $item['count'];
            }

            $queryTimeMs = round((microtime(true) - $t0) * 1000, 2);

            return [
                'labels'        => $labels,
                'data'          => $values,
                'query_time_ms' => $queryTimeMs,
            ];
        });
    }

    /**
     * Get most verified document types (Certificate vs LOR)
     */
    public function getMostVerifiedDocumentTypes(?string $institutionCode = null): array
    {
        $cacheKey = 'analytics_doc_types_' . ($institutionCode ?: 'all');

        return Cache::remember($cacheKey, 60, function () use ($institutionCode) {
            $t0 = microtime(true);

            $pipeline = [
                [
                    '$lookup' => [
                        'from'         => 'documents',
                        'localField'   => 'certificate_id',
                        'foreignField' => 'document_id',
                        'as'           => 'doc',
                    ]
                ],
                ['$unwind' => '$doc'],
            ];

            if ($institutionCode) {
                $pipeline[] = ['$match' => ['doc.issuer_id' => $institutionCode]];
            }

            $pipeline[] = [
                '$group' => [
                    '_id'   => '$doc.document_type',
                    'count' => ['$sum' => 1],
                ]
            ];
            $pipeline[] = ['$sort' => ['count' => -1]];

            $cursor = VerificationLog::raw(fn($col) => $col->aggregate($pipeline));

            $labels = [];
            $values = [];
            foreach ($cursor as $item) {
                $labels[] = ucwords(str_replace('_', ' ', (string) $item['_id']));
                $values[] = (int) $item['count'];
            }

            $queryTimeMs = round((microtime(true) - $t0) * 1000, 2);

            return [
                'labels'        => $labels,
                'data'          => $values,
                'query_time_ms' => $queryTimeMs,
            ];
        });
    }
}
