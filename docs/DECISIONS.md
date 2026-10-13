# AcademIQ — Engineering & Architectural Decisions

This document records the architectural and design decisions made throughout development.

---

### Decision 1: Keyed HMAC-SHA256 for Document Fingerprinting
- **Context:** Previously, `DocumentRecord::computeHash` computed a standard SHA-256 hash of pipe-delimited document fields.
- **Problem:** If an attacker or rogue administrator gains direct read/write access to MongoDB (e.g., via MongoDB Compass or SQL/NoSQL injection), they could tamper with student names, grades, or passing years and recalculate the plain SHA-256 hash. The verification algorithm would see the updated hash and falsely certify the document as genuine.
- **Decision:** Upgraded document hashing to **HMAC-SHA256** using `hash_hmac('sha256', implode('|', [...]), config('academiq.doc_hmac_secret'))`. The secret key (`DOC_HMAC_SECRET`) is stored in `.env` and never in the database.
- **Impact:** An attacker cannot forge a valid document hash without compromising the application server environment secrets.

### Decision 2: Cache Store Selection & Cache Layer Architecture
- **Context:** Redis is not installed by default on Windows/XAMPP environments.
- **Decision:** Default `CACHE_STORE` is set to `file`. All caching uses Laravel's `Cache` facade (`Cache::remember`, `Cache::forget`) with plain serialized associative arrays (not Eloquent/Mongo model objects) to avoid BSON/Unserialize issues.
- **Impact:** Seamless out-of-the-box operation on Windows, while switching to Redis requires only setting `CACHE_STORE=redis`, `REDIS_CLIENT=predis`, and `composer require predis/predis`.

### Decision 3: Storage of Dates as Real BSON UTCDateTime
- **Context:** Storing dates as ISO strings prevents MongoDB aggregation pipeline date operators (like `$dateToString`, `$hour`, `$dayOfYear`) from functioning efficiently.
- **Decision:** All datetime fields (`issue_date`, `timestamp`, `detected_at`, `created_at`, `updated_at`) are stored as Carbon / BSON `UTCDateTime` instances in MongoDB and cast properly in Eloquent models. Timezone is set to `Asia/Dhaka` for display formatting.

### Decision 4: Fallback Architecture for Fraud Detection
- **Context:** Fraud detection features (Isolation Forest anomaly detection, RapidFuzz string similarity) are hosted in an external Python FastAPI microservice (`fraud-service/`).
- **Decision:** `App\Services\FraudService` wraps HTTP calls with a 2-second timeout and try/catch handlers. If the Python microservice is down, unreachable, or errors, the Laravel application seamlessly falls back to fast rule-based Mongo queries (e.g., duplicate roll checks, request frequency counters). Verification and document issuance requests never fail due to microservice outages.
- **Background Anomaly Logging:** Anomaly scoring and tamper flagging on verification run asynchronously after the response (`dispatch(...)->afterResponse()`) to ensure zero latency overhead on verification lookups.

### Decision 5: Authentication & Minimal UI Architecture
- **Context:** Constraint states no npm or Vite build steps.
- **Decision:** Implemented clean, responsive session-based authentication using Blade and Bootstrap 5 CDN + Bootstrap Icons CDN. Standard Laravel session auth is used with custom role and approved middleware (`role:`, `approved`).

### Decision 6: Dual Document Hashing Scope (Certificates vs. LORs)
- **Context:** Certificates and Recommendation Letters (LORs) have different metadata fields.
- **Decision:**
  - For Certificates: HMAC covers `document_id | recipient_name | issuer_id | roll_no | passing_year`.
  - For Recommendation Letters (LORs): HMAC covers `document_id | recipient_name | issuer_id | purpose`.

### Decision 7: Load Testing Architecture & Concurrency Strategy
- **Context:** Running high-concurrency benchmarks (e.g. k6 with 200 virtual users) directly against `php artisan serve` fails because the built-in PHP development web server is single-threaded and handles requests strictly sequentially.
- **Decision:**
  - Designed `loadtest/verify.js` for execution with k6, with configurable target URL (`TARGET_URL`).
  - Added `academiq:export-ids` artisan command to safely pre-populate a pool of valid IDs without runtime database overhead during the test.
  - Documented requirement to execute k6 against Apache / Nginx / Octane and temporarily set `VERIFY_THROTTLE=10000,1` during stress testing.
