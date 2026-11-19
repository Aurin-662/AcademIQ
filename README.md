# AcademiQ

**Trust Academic Credentials. Verify Without Revealing.**
Academic credential verification for KUET: students hold signed credentials in a private browser wallet and prove selected claims to employers with Zero-Knowledge Proofs, with consent-based background checks.

Stack: Laravel 13 (PHP 8.3), MongoDB 7 (replica set) via `mongodb/laravel-mongodb`, Blade + Tailwind 4 + Alpine.js + Vite, Circom 2 + SnarkJS (Groth16), dompdf. Development runs in Docker.

## Quick start

Prerequisites: Docker Desktop (Compose v2).

```sh
cd academiq
cp .env.example .env
docker compose up -d mongo app vite
docker compose run --rm app composer install
docker compose run --rm app npm ci
docker compose run --rm app php artisan key:generate
docker compose run --rm app npm run build          # or keep the `vite` service running for dev
docker compose run --rm app php artisan zkp:register credential 1
docker compose run --rm app php artisan academiq:demo   # fictional demo accounts (local only)
```

App: http://localhost:8000. Demo walkthrough: [docs/DEMO.md](docs/DEMO.md).

First real admin (instead of demo data): `docker compose run --rm app php artisan academiq:make-admin you@kuet.example "Your Name"`, then authorize that admin as an issuer under Admin > Issuers.

Scheduler (expires stale requests/tasks): `docker compose run --rm app php artisan schedule:work`.

## Tests

```sh
docker compose run --rm app php artisan test      # 72 feature tests, uses database `academiq_test` (wiped per test; refuses non-*_test DBs)
docker compose run --rm app npm run test:zkp      # circuit tests (valid/invalid witnesses and proofs)
```

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel key. **Also encrypts issuer signing keys.** Losing it loses signing ability. |
| `MONGODB_URI`, `MONGODB_DATABASE` | MongoDB connection. A replica set is required (transactions). |
| `ISSUER_KEY_PATH` | Directory of encrypted issuer private keys (default `storage/app/issuer-keys`). Back up with `APP_KEY`. |
| `NODE_BIN` | Node binary for signing and proof verification (Node 20+). |
| `MAIL_*` | Email notifications. Only sent when `MAIL_MAILER` is a real mailer; otherwise in-app only and `emailed_at` stays empty. |
| `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `APP_DEBUG`, `APP_ENV` | Production hardening, see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). |

## Architecture (modular monolith)

```
app/Http/Controllers/{Admin,Student,Employer,External,Auth}   thin controllers
app/Services/Credentials   1.x issue/update/revoke, signing keys, field encoding
app/Services/ZKP           2.x proof intake + Groth16 verification
app/Services/Verification  3.x gateway (request, consent, routing) + collector
app/Services/BackgroundChecks  4.x police / referee / previous employer
app/Services/Reports       5.x PDF report + authenticity
app/Services/AuditLogger   append-only audit log
zkp/                       circuit, build script, verification keys, Node helpers
resources/js/wallet.js     holder-side encrypted wallet + in-browser prover
```

Logical stores. **D1 (credentials):** `credentials`, `credential_versions`, `authorized_issuers`, `verification_keys`, `student_references`, `credential_deliveries` (ciphertext only, deleted on receipt). **D2 (verification):** `verification_requests`, `consent_records`, `proof_submissions`, `proof_results`, `background_verifications`, `police_verifications`, `referee_responses`, `employment_verifications`, `verification_reports`, `audit_logs`. Platform: `users`, `student_profiles`, `employer_profiles`, `notifications`, `contact_messages`.

## Roles

`admin`, `issuer`, `student`, `employer`, `previous_employer`, `referee`, `police_verifier`. Only students and employers can self-register; every other account is provisioned by an admin.

## Documentation

- [docs/DEMO.md](docs/DEMO.md): repeatable end-to-end demo
- [docs/SECURITY.md](docs/SECURITY.md): privacy boundary, threat notes, known limitations
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md): production checklist
- [zkp/README.md](zkp/README.md): circuit, claims, setup, key rotation

## Honest limitations

- The trusted setup built by `zkp/build.sh` is a **single-party development ceremony**. Run a multi-party ceremony before production.
- Police, referee and employment checks are **manual responses** typed by authorized portal users. There is no integration with any police, government or KUET system, and the UI says so.
- The KUET logo is a replaceable placeholder; supply authorized branding files.
- MongoDB indexes/uniqueness live in `database/migrations/2026_01_01_000000_create_indexes.php`. Migrations are never run automatically; run `php artisan migrate` deliberately.
- Email verification at registration is not implemented.
