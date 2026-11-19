# Security and privacy

## Privacy boundary (enforced in code and tests)

1. Private credential data (values, issuer signature, salt) exists only on the holder side. The server builds it at issuance, seals it to the student's wallet public key (ECIES, P-256) and keeps only ciphertext until the wallet acknowledges receipt, then deletes it.
2. The wallet stores packages in IndexedDB encrypted with AES-GCM under a key derived from the student's passphrase (PBKDF2-SHA256, 600k iterations). There is no plaintext copy in localStorage.
3. Proof generation runs in the browser. The backend receives only the Groth16 proof and six public signals. A request carrying private-data fields is refused (422).
4. D1 stores commitments, status and metadata, never credential contents. Proofs are verified in memory and discarded; `proof_results` and `proof_submissions` hold only outcome metadata.
5. Employers receive an outcomes-only PDF. Participant remarks and police references are never shown to employers. Results of checks the applicant declined are never included.
6. Consent is validated (owner, decision, scope, expiry, not revoked) before any academic proof is accepted or background task is created. Withdrawing consent cancels outstanding work.
7. Every report download is audited; reports sit on a private disk (no public URL), are served only to the owning employer, and are hash-checked before serving.

## Keys (do not confuse them)

- **Issuer signing key** (EdDSA over BabyJubJub): authenticates credentials. Private half is stored encrypted with `APP_KEY` on disk (`ISSUER_KEY_PATH`), never in MongoDB, never in browser code. Public half is in `authorized_issuers`. Rotation: Admin > Issuers > Rotate key; the old key is retired, credentials it signed keep verifying until re-issued or revoked.
- **Groth16 verification key**: verifies proofs. Stored in `verification_keys` per circuit version. Retire a version in Admin > Verification keys to reject proofs made under it.

## Controls

CSRF on all forms; password hashing (bcrypt); login throttling (5 attempts per email+IP) and route rate limits; session regeneration on login; no account enumeration on password reset; role middleware plus scoped queries (another user's resource is 404); mass assignment guarded for `role`/`is_active`; NoSQL operator injection blocked by validating inputs as strings (tested); output escaping via Blade; security headers (CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS); append-only audit log (model refuses update/delete); atomic compare-and-set on single-use states (consent decision, proof submission, background response, update/revoke).

## Known limitations and residual risks

- CSP allows `'unsafe-eval'` (Alpine.js standard build) and `script-src-attr 'unsafe-inline'` (confirm dialogs). Moving to the Alpine CSP build and removing inline handlers would tighten this.
- The trusted setup is a single-party development ceremony. A compromised setup could allow forged proofs. Use a multi-party ceremony for production.
- If a student loses both wallet passphrase and backup, credentials cannot be recovered; KUET must re-issue. This is by design.
- Whoever holds `APP_KEY` plus the key directory can sign credentials. Protect both, restrict filesystem access, and consider an HSM or KMS-backed signer for production.
- A student who has a package can prove claims until the credential is revoked or superseded; revocation is enforced at verification time by the server.
- MongoDB uniqueness (emails, ids, one proof per request) is enforced by the index migration, which is not run automatically. Run it before production; until then the app-level checks still apply.
- Background results are only as trustworthy as the portal users KUET provisions.
- Audit-log append-only is enforced at the application layer. For stronger guarantees use a database role with insert-only rights on `audit_logs`.

Please report vulnerabilities privately to the KUET system owners.

## Dependency audit (at time of writing)

`composer audit`: no advisories. `npm audit --omit=dev` reports transitive issues under `snarkjs` (`ethers`, `ws`, `jsonpath`/`underscore`, `bfj`). AcademiQ calls only `snarkjs.groth16.fullProve` and `groth16.verify`, which do not use those code paths, but re-run `npm audit` and update `snarkjs`/`circomlibjs` regularly, and re-run `npm run test:zkp` after upgrades.
