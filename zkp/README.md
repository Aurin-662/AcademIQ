# ZKP: circuit, setup, keys

- Circuit: `circuits/credential.circom`, id `credential`, version 1. Groth16 over BN254, circomlib Poseidon and EdDSA-Poseidon.
- Credential commitment: `Poseidon(credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt)`. Strings encode as the first 31 bytes of SHA-256 (see `app/Services/Credentials/FieldEncoder.php`). The issuer signs the commitment with EdDSA-Poseidon.
- Public signals (in order): `commitment, Ax, Ay, request_id, claim_type, param`.
- Supported claims (bounded, fixed set): 1 CGPA >= param (x100), 2 programme == param, 3 degree == param, 4 issuer-only (param must be 0). Every proof also proves the issuer signature. Arbitrary claims are **not** supported.
- The server additionally checks that `Ax, Ay` match the registered issuer key, the commitment is the credential's current one, the credential is active, the request id and claim parameters match, and the circuit version is active.

## Build

```sh
docker compose run --rm app sh zkp/build.sh
docker compose run --rm app php artisan zkp:register credential 1
docker compose run --rm app npm run test:zkp
```

`build.sh` compiles, runs a **single-party development** powers-of-tau and phase 2 contribution, exports the verification key to `verification-keys/`, and copies `credential_v1.wasm` and `credential_v1.zkey` to `public/zkp/` (public by design: they contain no secrets). For production replace the ptau with a multi-party ceremony output.

## Two different keys

- Issuer signing key: authenticates credentials (EdDSA). Stored encrypted on the server, rotated per issuer.
- Groth16 verification key: checks proofs. Stored in `verification_keys`. Not interchangeable with the issuer key.

## Versioning and rotation

A change to the circuit means a new version: build it as `credential_v2.*`, register with `php artisan zkp:register credential 2 --retire-others`. The server rejects proofs under retired versions, and the wallet fetches the active version from the gateway instruction. Existing credentials remain valid if their commitment layout is unchanged; if the preimage changes, re-issue credentials.

## Tools

- `tools/crypto.mjs`: keygen, Poseidon commit, EdDSA sign/verify, ECIES seal (server side).
- `tools/verify.mjs`: Groth16 verification (server side).
- `tools/prove.mjs`: **development/test only** proving outside a browser; the real prover is in `resources/js/wallet.js`.
