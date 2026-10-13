# AcademIQ — Large-Scale Academic Credential Verification & Fraud Analytics

**AcademIQ** (working title: *EduVerify*) is a high-performance academic credential verification and fraud-analytics platform built on **Laravel 12**, **MongoDB**, **SQLite**, and an asynchronous **FastAPI ML Microservice**. It was developed for the CSE 3200 University System Development Project to demonstrate applied software engineering, database performance engineering, and intelligent fraud detection.

---

## 1. System Architecture

```text
                               +--------------------------------------------+
                               |              Public Client                 |
                               |    (QR Scanner / Web / Employer CSV Bulk)  |
                               +---------------------+----------------------+
                                                     | HTTPS
                                                     v
+---------------------------------------------------------------------------------------------------+
|                                 Laravel 12 Core Application                                       |
|                                                                                                   |
|  +------------------------+   +------------------------+   +-----------------------------------+  |
|  |     Authentication     |   |   Public Verification  |   |        Bulk Verification          |  |
|  | (RBAC, Approval Gates) |   |  (Cache-First Pipeline)|   |  (Chunked Queue / Async Process)  |  |
|  +-----------+------------+   +-----------+------------+   +-----------------+-----------------+  |
|              |                            |                                  |                    |
|              | SQLite                     | Cache Miss                       | SQLite             |
|              v                            v                                  v                    |
|  +------------------------+   +------------------------+   +-----------------------------------+  |
|  |     Users / Auth DB    |   |  DocumentRecord Model  |   |   bulk_verification_requests DB   |  |
|  |   (database.sqlite)    |   |     (MongoDB BSON)     |   |         (database.sqlite)         |  |
|  +------------------------+   +-----------+------------+   +-----------------------------------+  |
|                                           |                                                       |
|                                           |                                                       |
|                     +---------------------+---------------------+                                 |
|                     | Hook 1: Document Issue                    | Hook 2: Verification Flow       |
|                     | - Duplicate Roll Checks                   | - Anomaly Scoring (Rate/Bursts) |
|                     | - RapidFuzz String Match                  | - Keyed HMAC-SHA256 Integrity   |
|                     +---------------------+---------------------+                                 |
|                                           |                                                       |
+-------------------------------------------|-------------------------------------------------------+
                                            | HTTP POST (JSON, 2.0s timeout with fallback)
                                            v
                      +-------------------------------------------+
                      |         FastAPI Fraud Microservice        |
                      |          (Python 3.10+ / Uvicorn)         |
                      |                                           |
                      |  - /fuzzy-check: RapidFuzz token match    |
                      |  - /anomaly-score: Scikit-learn           |
                      |    IsolationForest trained model          |
                      |  - Rule-based fallback if ML offline      |
                      +-------------------------------------------+
```

---

## 2. Key Engineering Highlights

1. **High-Performance MongoDB Indexing & Caching Pipeline:**
   - Evaluated across 5,200+ documents with empirical benchmark comparisons.
   - Cache-first architecture using plain PHP arrays drops verification latency to **1.84ms** (p95: **2.21ms**), yielding a **3.5x throughput gain** (543.9 req/s).
   - Automated cache invalidation via Eloquent lifecycle model events (`saved`, `deleted`).
2. **Cryptographic Integrity & Threat Model (Keyed HMAC-SHA256):**
   - Documents are signed with a keyed HMAC-SHA256 signature using `DOC_HMAC_SECRET`.
   - **Threat Model Defense:** If a malicious actor gains direct read/write access to the MongoDB instance (e.g. via MongoDB Compass or stolen credentials), they can insert counterfeit records or alter grades, but **cannot compute a valid hash signature**. Verification recalculates the keyed HMAC on the fly and immediately flags forged documents as `tampered` and `hash_mismatch`.
3. **Applied ML & Fraud Anomaly Engine:**
   - **Hook 1 (Issue):** Checks for duplicate roll allocations and fuzzy-similar student names using `RapidFuzz` (WRatio > 85).
   - **Hook 2 (Verification):** Gathers 1-hour verification frequency, IP dispersion, and failure rates to score anomalies with a scikit-learn `IsolationForest` model.
   - **Resilience:** If the Python microservice is unreachable, Laravel automatically falls back to an internal rule-based heuristic with zero service downtime.
4. **Bulk Verification with Memory Safety:**
   - Handles enterprise CSVs up to 5,000 records using chunked `whereIn` batching (500 per query) and streamed CSV exports.
5. **Native SVG QR Codes:**
   - Vector-only QR codes generated using BaconQrCode/BMS without requiring `ext-imagick`.

---

## 3. Prerequisites

- **Operating System:** Windows 10/11, Linux, or macOS.
- **PHP:** PHP 8.2+ with `mongodb`, `curl`, `mbstring`, `pdo_sqlite` extensions enabled.
- **Database:** MongoDB Community Server 5.0+ running on `mongodb://127.0.0.1:27017`.
- **Python:** Python 3.10+ for the FastAPI fraud microservice.
- **Composer:** Composer 2.x for PHP dependency management.
- **k6 (Optional):** For executing API load tests.

---

## 4. Windows Quick Start (Step-by-Step)

### Step 1: Clone and Configure Environment
Open PowerShell or CMD in the project root:
```powershell
# Copy environment configuration
copy .env.example .env

# Install PHP dependencies
composer install

# Generate application key
php artisan key:generate
```

Ensure `.env` contains:
```env
DB_CONNECTION=sqlite
DB_DATABASE=e:\3-2\AcademIQ\AcademIQ\database\database.sqlite

MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=academiq

DOC_HMAC_SECRET=academiq_hmac_secret_key_demo_change_in_production_2026
FRAUD_SERVICE_URL=http://127.0.0.1:8001
CACHE_STORE=file
```

### Step 2: Initialize Database and Indexes
```powershell
# Run SQLite relational migrations
php artisan migrate

# Apply MongoDB compound, unique, and single indexes
php artisan academiq:indexes

# Seed default roles, institutions, and demo users
php artisan db:seed --class=DemoSeeder

# Seed baseline certificates and verification logs
php artisan academiq:import
```

### Step 3: Setup Fraud Microservice Environment
```powershell
cd fraud-service

# Create Python virtual environment
python -m venv .venv

# Activate virtual environment
.\.venv\Scripts\activate

# Install Python requirements
pip install -r requirements.txt

# Train IsolationForest model
python train.py

cd ..
```

### Step 4: Run AcademIQ
You can launch both the Laravel web application and FastAPI microservice simultaneously using the provided Windows script:
```powershell
.\run-dev.bat
```
Or start them manually in separate terminal windows:
```powershell
# Terminal 1: Laravel Web App
php artisan serve --port=8000

# Terminal 2: Fraud Microservice
.\fraud-service\.venv\Scripts\uvicorn main:app --app-dir fraud-service --port 8001
```

- **Web Portal:** [http://127.0.0.1:8000](http://127.0.0.1:8000)
- **FastAPI Interactive Docs:** [http://127.0.0.1:8001/docs](http://127.0.0.1:8001/docs)

---

## 5. Demo Accounts & Credentials

All accounts share the default password: **`password`**

| Role | Email | Institution / Org | Permissions & Features |
|---|---|---|---|
| **System Admin** | `admin@academiq.edu` | AcademIQ Platform | User approvals, teacher verification toggle, document revocation, fraud alert review, system-wide analytics |
| **Institution Admin** | `admin@kuet.ac.bd` | Khulna University of Engineering & Technology (KUET) | Institution analytics, batch credential issuance, student records |
| **Institution Admin** | `admin@buet.ac.bd` | Bangladesh University of Engineering & Technology (BUET) | Institution analytics, batch credential issuance |
| **Verified Teacher** | `prof.ahmed@kuet.ac.bd` | KUET (Dept. of CSE) | Issue Recommendation Letters (LOR), public verified faculty badge, student letter search |
| **Unverified Teacher** | `lecturer.hasan@buet.ac.bd` | BUET | Pending admin verification; issue actions restricted |
| **Pending User** | `applicant@aust.edu` | AUST | Pending admin account approval |
| **Employer** | `hr@brainstation-23.com` | Brain Station 23 | Bulk CSV verification, request history, audit exports |
| **Employer** | `talent@kaz.com.bd` | Kaz Software | Bulk CSV verification, request history |

---

## 6. Artisan Commands Reference

| Command | Description | Example Usage |
|---|---|---|
| `academiq:indexes` | Creates required unique and compound indexes on MongoDB collections (`documents`, `verification_logs`, `fraud_alerts`). | `php artisan academiq:indexes` |
| `academiq:import` | Imports initial sample certificates and verification logs into MongoDB. | `php artisan academiq:import` |
| `academiq:seed-large` | Populates database with synthetic documents (5,000+ default) for benchmark testing. | `php artisan academiq:seed-large --count=5000` |
| `academiq:benchmark` | Measures cold unindexed, cold indexed, and warm cached read latencies across random samples. | `php artisan academiq:benchmark --samples=500` |
| `academiq:simulate-attack` | Simulates high-rate credential enumeration attacks to demonstrate live anomaly detection and fraud alerts. | `php artisan academiq:simulate-attack --rate=50` |
| `academiq:export-ids` | Exports a pool of valid document IDs to `loadtest/valid_ids.json` for k6 testing. | `php artisan academiq:export-ids 500` |

---

## 7. Running the Test Suite

AcademIQ includes a test suite covering authentication, role-based access control, public verification, SVG QR generation, teacher LOR issuance, bulk CSV processing, fraud microservice hooks, and MongoDB aggregation analytics.

```powershell
php artisan test
```

Expected result:
```text
PASS  Tests\Unit\ExampleTest
PASS  Tests\Feature\ExampleTest
PASS  Tests\Feature\AuthAndRoleTest
PASS  Tests\Feature\VerificationTest
PASS  Tests\Feature\TeacherAndAdminTest
PASS  Tests\Feature\BulkVerificationTest
PASS  Tests\Feature\FraudDetectionTest
PASS  Tests\Feature\AnalyticsTest

Tests:    23 passed (103 assertions)
Duration: ~4.5s
```

---

## 8. Threat Model: Keyed HMAC-SHA256 vs. Database Tampering

```text
[Malicious Database Administrator / Attacker with Compass Access]
                          |
                          v
         Alters: "cgpa": 2.80  -->  "cgpa": 3.95
                          |
                          | (Cannot forge HMAC without DOC_HMAC_SECRET)
                          v
        [AcademIQ Verification Pipeline: GET /verify/{id}]
                          |
                          +--> Calculates: HMAC(cgpa=3.95, salt, roll, ...)
                          +--> Compares to stored "doc_hash"
                          |
                          v
               MISMATCH DETECTED!
               Result: Document Marked TAMPERED
               Action: High-Priority Fraud Alert Logged
```

- In standard systems using simple SHA-256 or MD5 hashes without a secret, an attacker with write access to the database can alter any field and simply recompute `sha256(student_name + roll + cgpa)` to match.
- **AcademIQ Solution:** Fingerprints are generated via `hash_hmac('sha256', $canonicalString, config('academiq.doc_hmac_secret'))`. The secret key is stored exclusively in application environment variables (`.env`) and never stored in MongoDB. Any direct modification to the record results in instant cryptographic invalidation upon scan.
