# AcademIQ Performance & Benchmark Engineering Report

This document records empirical latency, throughput, and optimization benchmarks for the **AcademIQ** credential verification pipeline.

---

## 1. Hardware & Environment Specifications

- **Operating System:** Windows 10 / 11 (64-bit)
- **Web Server Runtime:** XAMPP Apache 2.4 / PHP CLI Development Server
- **PHP Version:** PHP 8.2.12 ZTS x64
- **PHP MongoDB Extension:** `mongodb` 1.15+ (BSON v2)
- **Database Backend (Relational):** SQLite 3 (users, sessions, jobs, bulk requests)
- **Database Backend (Document):** MongoDB Community Server 5.x / 7.x
- **Cache Storage Driver:** Laravel File Driver (`storage/framework/cache/data`) & Redis (Production Ready)
- **Machine Specs:** Multi-core Intel/AMD x86_64, NVMe SSD storage

---

## 2. Empirical Benchmark Results

Generated via `php artisan academiq:benchmark --samples=500` across a database populated with **5,200+** certificates and transcripts.

### Results Summary Table

| Benchmark Configuration | Avg Latency | p95 Latency | Min Latency | Max Latency | Throughput | Relative Speedup |
|---|---|---|---|---|---|---|
| **(a) Unindexed (Cold MongoDB)** | **6.36 ms** | **9.20 ms** | 3.45 ms | 33.92 ms | **157.1 req/s** | Baseline (1.0x) |
| **(b) Indexed (Cold MongoDB)** | **6.15 ms** | **8.46 ms** | 3.33 ms | 18.84 ms | **162.7 req/s** | 1.04x |
| **(c) Indexed + Cache Warm (File Store)** | **1.84 ms** | **2.21 ms** | 1.41 ms | 7.50 ms | **543.9 req/s** | **3.5x (~250% boost)** |

*Note: In large enterprise deployments with hundreds of thousands of records, the unindexed `COLLSCAN` latency increases linearly O(N), whereas indexed B-tree lookups stay flat O(log N).*

---

## 3. In-Depth Technical Analysis

### A. Scanned vs. Returned Documents (`COLLSCAN` vs. `IXSCAN`)
- **Without Indexes (`COLLSCAN`):** MongoDB queries against `document_id` or `{ roll_number, institution_code }` must inspect every document in the collection. Even at modest collection sizes (5,000 to 50,000 items), scanning documents burns CPU cycles and discards memory pages.
- **With Compound & Unique Indexes (`IXSCAN`):** 
  - Applying `{ document_id: 1 }` (unique) ensures O(log N) B-Tree key lookup.
  - Ratio of `totalKeysExamined` : `totalDocsExamined` : `nReturned` is exactly **1 : 1 : 1**.
  - Compound indexes on `{ institution_code: 1, issue_date: -1 }` allow instant pagination and sorting without in-memory sorting (`SORT` stage avoided).

### B. Why Application Cache Outperforms Indexed Lookups
While MongoDB's indexed query takes ~6ms, Laravel application-level caching (`Cache::remember`) drops latency to **1.84ms** (p95: **2.21ms**), a **3.5x throughput gain** (543.9 req/s on a single PHP thread).
1. **Network & IPC Overhead Elimination:** Even with localhost MongoDB connections, TCP socket handshake, wire protocol framing (OP_MSG), and context switching add ~3-4ms of baseline latency.
2. **BSON Serialization Bypass:** Mongo queries require deserializing binary BSON into PHP objects. Storing serialized plain PHP arrays in cache allows instant array hydration.
3. **Low Memory Footprint:** Storing plain arrays (instead of full Eloquent model instances) avoids serialization errors and prevents memory bloat in PHP workers.

### C. Cache Invalidation Architecture
A common risk with caching credential records is serving revoked or updated certificates from stale cache. AcademIQ solves this at the model lifecycle level:
- In `App\Models\DocumentRecord::booted()`, lifecycle event hooks are registered:
  ```php
  static::saved(function ($doc) {
      Cache::forget("doc:{$doc->document_id}");
  });
  static::deleted(function ($doc) {
      Cache::forget("doc:{$doc->document_id}");
  });
  ```
- Whenever an administrator revokes a certificate via `POST /admin/documents/{id}/revoke` or modifies student data, the cache key is purged immediately. The subsequent verification request performs a fresh lookup and immediately reflects the revoked status.

---

## 4. Production Redis Migration (Windows / Linux)

To scale AcademIQ horizontally across multiple load-balanced web servers, migrate the cache store from local files to Redis.

### Configuration Changes
In `.env`:
```env
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Running Redis on Windows
1. **Option 1: Memurai (Native Windows Redis)**
   - Download Memurai Developer Edition from [memurai.com](https://www.memurai.com/).
   - Runs as a native 64-bit Windows service compatible with Redis 5/6/7 protocols.
   - Start service: `net start memurai`
2. **Option 2: WSL2 (Windows Subsystem for Linux)**
   ```bash
   wsl -d Ubuntu
   sudo apt update && sudo apt install redis-server -y
   sudo service redis-server start
   ```
3. **Verify Connection:**
   ```powershell
   php artisan cache:clear
   php artisan tinker --execute="Cache::put('ping', 'pong', 60); echo Cache::get('ping');"
   ```

---

## 5. High-Concurrency Load Testing with k6

AcademIQ includes a dedicated load testing suite located in `loadtest/`.

### Setup Instructions
1. **Export target IDs pool:**
   ```powershell
   php artisan academiq:export-ids 500
   ```
2. **Adjust Rate Limiter in `.env` for testing:**
   ```env
   # Increase throttle limit so load tester is not prematurely 429'd
   VERIFY_THROTTLE=10000,1
   ```
3. **Run k6:**
   ```powershell
   k6 run loadtest/verify.js
   ```

### Load Profile (Ramping from 10 to 200 VUs)
- **10 VUs for 15s:** System warm-up & cache population
- **50 VUs for 20s:** Sustained baseline traffic
- **200 VUs for 15s:** Extreme stress spike
- **Target Pass Criteria:**
  - `http_req_duration (p95)` < 50ms
  - `http_req_failed` < 1.0%

> **Important Note:** Do not run k6 against `php artisan serve`, as PHP's built-in CLI server is single-threaded and handles requests synchronously. Run against Apache (XAMPP VirtualHost), Nginx, or Laravel Octane (FrankenPHP) for multi-worker concurrency.
