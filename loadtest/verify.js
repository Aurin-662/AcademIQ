/**
 * AcademIQ - k6 API Verification Load Test Script
 *
 * HOW TO RUN:
 *   k6 run loadtest/verify.js
 *
 * WARNING:
 *   Do not run k6 against `php artisan serve` (single-threaded PHP built-in server).
 *   Run it against Apache (e.g. XAMPP VirtualHost), Nginx, or Laravel Octane (FrankenPHP/Swoole).
 *   Also raise or disable VERIFY_THROTTLE in .env before testing:
 *     VERIFY_THROTTLE=10000,1
 *
 * THRESHOLDS:
 *   - 95th percentile response time < 50ms (when warm/indexed)
 *   - Error rate < 1%
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

// Load pre-generated valid document IDs exported via `php artisan academiq:export-ids 500`
const validIds = new SharedArray('valid document IDs', function () {
    const data = JSON.parse(open('./valid_ids.json'));
    return data;
});

const BASE_URL = __ENV.TARGET_URL || 'http://127.0.0.1:8000';

export const options = {
    stages: [
        { duration: '15s', target: 10 },   // Warm-up ramp to 10 VUs
        { duration: '20s', target: 50 },   // Step up to 50 VUs
        { duration: '15s', target: 200 },  // Peak load ramp to 200 VUs
        { duration: '10s', target: 0 },    // Cool-down
    ],
    thresholds: {
        http_req_duration: ['p(95)<50'], // 95% of requests must complete within 50ms
        http_req_failed: ['rate<0.01'],  // Less than 1% errors
    },
};

export default function () {
    // Pick random document ID from pool
    const randomId = validIds[Math.floor(Math.random() * validIds.length)];
    const url = `${BASE_URL}/api/verify/${randomId}`;

    const res = http.get(url, {
        headers: {
            'Accept': 'application/json',
            'User-Agent': 'k6-load-tester/1.0',
        },
    });

    const isSuccess = check(res, {
        'status is 200': (r) => r.status === 200,
        'document is valid': (r) => {
            try {
                const body = r.json();
                return body.valid === true;
            } catch (e) {
                return false;
            }
        },
    });

    // Small random think time between 10ms and 50ms
    sleep(0.01 + Math.random() * 0.04);
}
