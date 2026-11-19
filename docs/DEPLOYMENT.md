# Deployment checklist

1. **Runtime:** PHP 8.3 with the `mongodb`, `gmp`, `intl`, `bcmath`, `zip`, `gd` extensions; Node 20+ on the server (signing and proof verification run `node zkp/tools/*.mjs`); MongoDB 7 as a **replica set** (transactions are used); circom 2.x only needed to rebuild circuits.
2. **Code:** `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. **Environment:** `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY` (`php artisan key:generate`), `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, real `MAIL_*` (optional), `MONGODB_URI` with credentials/TLS, `ISSUER_KEY_PATH` outside the web root with mode 0700.
4. **HTTPS only.** Terminate TLS at the proxy; set trusted proxies if needed. HSTS is sent automatically on HTTPS requests.
5. **Web root = `public/`.** Never expose `storage/` or `zkp/build/`. Reports live in `storage/app/private/reports`.
6. **Indexes (explicit step):** review then run `php artisan migrate` to create unique indexes (`database/migrations/2026_01_01_000000_create_indexes.php`). Migrations are never run automatically.
7. **ZKP artifacts:** build with a **multi-party** trusted setup, put `credential_v1.wasm` and `.zkey` in `public/zkp/`, the verification key in `zkp/verification-keys/`, then `php artisan zkp:register credential 1`.
8. **First admin:** `php artisan academiq:make-admin ...`; authorize an issuer in Admin > Issuers (generates the signing key). **Do not run `academiq:demo` in production** (it refuses unless `APP_ENV` is local/testing).
9. **Scheduler:** cron `* * * * * php artisan schedule:run` (runs `academiq:expire-requests` hourly).
10. **Backups:** MongoDB, `ISSUER_KEY_PATH` and `APP_KEY` together (keys are useless without `APP_KEY`), and `storage/app/private/reports`.
11. **Monitoring:** watch `audit_logs` for `auth.login_throttled`, `proof.rejected`, `report.integrity_failure`, `report.failed`. Failed report renders can be retried with `php artisan academiq:generate-report <request_id>`.
12. **Rotation:** issuer keys (Admin > Issuers), circuit versions (`zkp:register ... --retire-others`), `APP_KEY` (re-encrypt issuer keys before changing it).
13. **Branding:** replace `public/images/logo-placeholder.svg` with authorized KUET assets.
