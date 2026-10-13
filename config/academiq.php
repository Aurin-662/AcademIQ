<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AcademIQ Core Settings & Tunables
    |--------------------------------------------------------------------------
    */

    // Cache TTL for verified documents (in seconds)
    'cache_ttl' => (int) env('ACADEMIQ_CACHE_TTL', 3600),

    // Secret key for HMAC-SHA256 generation & verification
    'doc_hmac_secret' => env('DOC_HMAC_SECRET', 'academiq_secret_key_default'),

    // RapidFuzz string similarity threshold (0 - 100)
    'fuzzy_threshold' => (float) env('FRAUD_FUZZY_THRESHOLD', 85.0),

    // Python FastAPI Fraud Detection Service URL
    'fraud_service_url' => env('FRAUD_SERVICE_URL', 'http://127.0.0.1:8001'),

    // Fallback anomaly detection thresholds (when python service is unreachable)
    'anomaly_requests_10m' => (int) env('ANOMALY_REQUESTS_10M', 30),
    'anomaly_not_found_ratio' => (float) env('ANOMALY_NOT_FOUND_RATIO', 0.5),

    // Whether to mask recipient names on public pages (e.g., "R. I.")
    'mask_recipient' => (bool) env('MASK_RECIPIENT_NAME', false),

    // Rate limiting for public verification routes (e.g., '60,1' or '10000,1' for load tests)
    'verify_throttle' => env('VERIFY_THROTTLE', '60,1'),
];
