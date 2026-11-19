# AcademIQ Change Request v3: Progress

## Decisions (agreed with owner)
- Adapt to the existing stack: Laravel 13 + MongoDB, EdDSA-signed Poseidon commitments. No Merkle tree.
- Role mapping: `kuet_issuer` = `issuer`/`admin` (existing), `verifier` = `employer`.
- "Merkle root / new root per batch" becomes: per-credential status check + a `batch_digest` (sha256 over the batch's sorted commitments) on each issuance batch.
- Credential schema extended with `institution_code`, `passing_year`, `name`. **In Phase 1 these ride in the sealed package under `profile` with `committed:false`** (display-only: not in the Poseidon commitment, not provable). Phase 2 moves them into the commitment with circuit v2. Credentials issued in Phase 1 must be re-issued after Phase 2 to be provable on the new claims. Circuit v1 proofs keep working unchanged.

## Phase 1: bulk / dynamic issuance: DONE (not executed, see below)
- 1.1 CSV/XLSX import, template download, per-row validation preview (`BulkImport`, `ImportController`, `admin/import/*`)
- 1.2 Smart single form: student lookup autofill (name, department), dropdowns (institution, department, degree, passing year), numeric CGPA 0.00-4.00
- 1.3 Batch issue: crypto in 3 Node processes total (`batch` op), all credentials + batch record in one Mongo transaction (`CredentialService::issueBatch`)
- 1.4 Result screen with issued/failed counts, error report CSV
- 1.5 Delivery to student wallet unchanged (sealed package); bulk revoke by student ID list
- New collection `issuance_batches` (no migration needed; Mongo creates it). Credentials get a `batch_id` field.
- Config: `IMPORT_MAX_ROWS` (default 500), `academiq.institutions`, `academiq.aliases`.

### Verification status (honest)
No PHP, Composer or running Docker daemon was available in the authoring environment. **The PHP code and the new tests (`tests/Feature/BulkIssueTest.php`) have not been executed.** `crypto.mjs` passes `node --check` only. Run before accepting the phase:
```
docker compose run --rm app php artisan test
npm run build        # new Blade views use Tailwind classes; rebuild assets
```

### Known limits
- XLSX reader is minimal (first sheet, plain values, no formulas/dates). CSV is the safe path.
- Bulk issue runs synchronously (about 3 Node processes regardless of size). Fine for 500 rows; the queue arrives with Phase 4.
- Issued `issue_date` for imports is the import day.

## Phase 2: claim-set circuit + claim selection + token generation: DONE (not executed, see below)
- Circuit `zkp/circuits/claimset.circom` (id `claimset`, v1). Kept separate from `credential` so the employer request flow is untouched.
  - Issuer now signs a second commitment `commitment_v2 = Poseidon(c8, institution_code, passing_year)` at issuance (`signature_v2` in the package, `commitment_v2` in D1). v1 commitment/signature unchanged.
  - Outputs: 7 CGPA ladder flags (2.0 2.5 3.0 3.25 3.5 3.75 4.0), year-range flag, institution/degree/department equality flags, optional revealed CGPA. Public selectors tell NOT PROVEN apart from FAIL. Proof is bound to the token id.
  - Public signal map is in the circuit header and `TokenService` (27 signals).
- `TokenService`: `prepare` (server fixes public parameters, reserves token id) then `submit` (checks every public signal against the reservation, credential status, current `commitment_v2`, issuer key; Groth16 verify; single-use gate). Token id = 80 random bits, `ACQ-XXXX-XXXX-XXXX-XXXX` (longer than the example in the spec to meet the >=64-bit rule).
- Collections: `verification_tokens` (token_id, credential_id, commitment_v2, proof, public_signals, claims, expires_at, status, consented_at), `token_usage_logs` (model only; rows are written in Phase 3).
- Consent: recorded on the token (`consented_at`, audit `token.created`); revoking the token withdraws it (`token.revoked`). No `consent_records` row, because that collection is keyed per employer request.
- Student UI: `/student/tokens` (list), `/student/tokens/new` (claim checkboxes, CGPA threshold or reveal, year range, institution/degree/department equality, expiry 7 to 365 days), token page (copy token, copy link, claims, revoke, usage log). Browser proving: `wallet.js proveClaims`.
- Not yet: QR code, PNG/CV badge, `/v/{token}` verification link (Phase 3).
- Phase-1 credentials have no `commitment_v2`: they must be re-issued (the UI says so).

### To enable (nothing below has been run)
```
docker compose run --rm app sh zkp/build.sh claimset 1        # compiles circuit, dev trusted setup, writes public/zkp/claimset_v1.* and the vkey
docker compose run --rm app php artisan zkp:register claimset 1
docker compose run --rm app php artisan test
docker compose run --rm app npm run test:zkp
docker compose run --rm app npm run build
```
Authoring environment had no PHP, circom or Docker, so the circuit has not been compiled and no PHP/JS test has run. If compile fails, the circuit is the first place to look.
Optional unique index on `verification_tokens.token_id`: `database/migrations/2026_02_01_000000_token_indexes.php` (run only when you decide to).

## Phase 3: QR + verifier single check + criteria builder: DONE (not executed)
- Student: token page shows the QR (encodes only `{APP_URL}/v/{token}`), Download QR (PNG), Download CV badge (PNG: QR + token + claims summary + expiry), copy token/link, usage log (verifier, outcome, time). QR and badge are made in the browser (`qrcode` npm package, `resources/js/qr.js`).
- Verifier (= `employer` role, login required): `/employer/verify` opens a token by typed id, pasted link or QR (camera scan or uploaded image, decoded locally with `jsqr`). `/v/{token}` (the QR target) sends guests to login and back, forwards verifiers, 403 for other roles.
- Criteria builder offers only what the token proves: CGPA ladder dropdown, passing year (from/to; same year = exact, from only = "or later"), institution, degree, department. Criteria combine with AND.
- Result per criterion PASS / FAIL / NOT PROVEN plus overall (any FAIL = FAIL, all PASS = PASS, else NOT PROVEN). Rules in `TokenVerifier::evaluate`:
  - Unselected claim = NOT PROVEN. CGPA with ladder: flag decides. CGPA revealed by holder: exact comparison.
  - Year: proven range inside the criterion = PASS, disjoint = FAIL, partial overlap = NOT PROVEN; "proven outside range" only gives FAIL when the range covers the criterion.
  - Equality: expected code equals the criterion -> flag decides; different code -> FAIL if the flag says equal, else NOT PROVEN.
- Backend check order per verification (`TokenVerifier::check`): token exists and active, not expired, not revoked -> credential active -> `commitment_v2` still current (superseded = out of date) -> issuer authorized and key matches signals -> Groth16 verify of the stored proof -> evaluate criteria. Every check, valid or not, writes a `token_usage_logs` row (outcomes only) and an audit entry (`token.verified` / `token.verify_failed`). Invalid reasons: unknown_token, revoked, expired, credential_revoked, credential_superseded, issuer_not_authorized, invalid_proof.
- Result page, Print, and PDF report (outcomes only, dompdf). Results are visible only to the verifier who ran the check.
- Lookups and checks are throttled per user (30 to 60 per minute). `Permissions-Policy` now allows `camera=(self)` for scanning.
- `package.json` gains `qrcode` and `jsqr`: run `npm install` (not `npm ci`) so the lockfile updates, then `npm run build`.
- Tests: `tests/Feature/VerifyTest.php` (criteria matrix, acceptance case, expired/revoked token, revoked/superseded credential, tampered proof, mismatched signals, access control, PDF, student usage log). It mocks only the Groth16 call; real proofs are covered by `TokenTest` and the circuit tests. Not executed here.
- Limits: a token carries no candidate identity, so the verifier ties it to a person through the CV. Verifier role = `employer`; there is no separate verifier registration.
## Phase 4: bulk verification: DONE (not executed)
- Input (any mix, in this order): CSV/XLSX with a `token_id` column (optional `candidate_name`, `cv_ref`), pasted list (one per line, optional `token, name, cv_ref`), and a multi-scan QR session (camera or several image uploads, deduplicated in the browser). Criteria are set once with the same fields as the single check and applied to every token.
- Pipeline (`BatchVerifier`): `create()` stages one `verification_batch_items` row per input line and marks `malformed` (not a token id) and `duplicate` (repeat of an earlier row, not re-checked); `step()` claims pending rows atomically and runs each through the same `TokenVerifier::check` as a single verification, so every real token gets the full gating, a `token_usage_logs` row (with `batch_id`) and a `token.verified` / `token.verify_failed` audit entry. The batch completes itself once and writes `bulk.completed`. Rows stuck in `processing` for over 2 minutes return to the queue.
- Background processing, two drivers over the same code: with a real queue (`QUEUE_CONNECTION` not `sync`) `ProcessVerificationBatch` runs the batch on a worker and the page only polls; with the default `sync` driver the progress page calls `POST /employer/bulk/{id}/step` repeatedly (10 tokens per request) so no request runs long and closing the tab loses nothing (reopen the batch to continue). Progress bar in both cases.
- Results table: token, candidate, each criterion PASS / FAIL / NOT PROVEN, overall, reason (expired, revoked, credential revoked/superseded, issuer, bad proof, unknown token, malformed, duplicate). Filters: all, passed, failed, not proven, invalid, duplicates. Sort by row, token, candidate, overall. Export CSV (formula-injection guarded) and PDF (landscape). Batches are saved in D2 (`verification_batches`, `verification_batch_items`) and listed under Recent batches; only the creating verifier can open them.
- Limits: `BULK_MAX_TOKENS` (default 500) per batch; throttles: create 6/min, step and progress 120/min, exports 20/min, file size 2 MB; duplicate detection as above.
- Tests: `tests/Feature/BulkVerifyTest.php` (mixed batch with PASS/FAIL/expired/revoked/unknown/malformed/duplicate, chunked progress and endpoints, stale claim recovery, limits, CSV input, filters/sort/CSV/PDF with injection guard, tampered proof, revoked credential, access control). Groth16 mocked as in VerifyTest. Not executed here.
- Fixed in passing: the Phase 1 student-lookup script was an inline `<script>`, which the CSP blocks. It is now the Alpine component in `resources/js/lookup.js`.
- Known limits: with the sync driver a batch only advances while the progress page is open; a very large PDF export (500 rows) may be slow; no resume of a half-uploaded file.
## Phase 5: tests, polish, docs: TODO

## UI refresh (views and CSS only; no controller, service, model or middleware changes)
- Design: institutional look. Serif display headings (Source Serif 4) with Inter body, flat navy bands with a fine grid instead of gradients, hairline-bordered cards, grouped sidebar with active indicator and role footer, page titles with subtitles.
- New components: `x-stepper` (issue: upload > review > issued; verify: open token > criteria > result), `x-guide` (collapsible how-it-works panel on the admin, employer and student dashboards).
- Public site: new home (role entry cards, process, assurance), role guides `/issuers`, `/students`, `/employers` (now "For verifiers") sharing `pages/_role.blade.php`, "How it works" with per-role tabs and a who-can-see-what table, header/footer rebuilt, split-panel sign in and register.
- Only route change: `GET /issuers` (static view). All form fields, ids, CSRF, and routes used by JS and tests are unchanged.
- Not verified visually (no running app in the authoring environment). Run `npm run build` and click through each role.
