import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import * as snarkjs from 'snarkjs';
import { buildEddsa, buildPoseidon } from 'circomlibjs';

const WASM = 'public/zkp/credential_v1.wasm';
const ZKEY = 'public/zkp/credential_v1.zkey';
const VKEY = JSON.parse(readFileSync('zkp/verification-keys/credential_v1.vkey.json', 'utf8'));
const eddsa = await buildEddsa();
const poseidon = await buildPoseidon();
const F = eddsa.F;

const priv = randomBytes(32);
const pub = eddsa.prv2pub(priv).map((x) => F.toString(x));

function credential({ cgpa = 352, programme = 1, degree = 1, signer = priv } = {}) {
  const fields = [11n, 22n, BigInt(cgpa), BigInt(programme), BigInt(degree), 20260601n, 1n, 987654321n];
  const commitment = poseidon.F.toString(poseidon(fields));
  const sig = eddsa.signPoseidon(signer, F.e(BigInt(commitment)));
  return { fields, commitment, R8: sig.R8.map((x) => F.toString(x)), S: sig.S.toString() };
}
function input(c, claim_type, param, extra = {}) {
  const [credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt] = c.fields.map(String);
  return { credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt,
    R8x: c.R8[0], R8y: c.R8[1], S: c.S, commitment: c.commitment, Ax: pub[0], Ay: pub[1], request_id: '424242', claim_type: String(claim_type), param: String(param), ...extra };
}
const prove = (i) => snarkjs.groth16.fullProve(i, WASM, ZKEY);
const rejects = (i) => assert.rejects(prove(i));

test('valid proofs verify for each claim type', async () => {
  const c = credential();
  for (const [t, p] of [[1, 300], [1, 352], [2, 1], [3, 1], [4, 0]]) {
    const { proof, publicSignals } = await prove(input(c, t, p));
    assert.equal(publicSignals.length, 6);
    assert.equal(publicSignals[0], c.commitment);
    assert.equal(publicSignals[3], '424242');
    assert.equal(await snarkjs.groth16.verify(VKEY, publicSignals, proof), true, `claim ${t}`);
  }
});

test('unsatisfied claims cannot produce a proof', async () => {
  const c = credential();
  await rejects(input(c, 1, 353)); // cgpa below threshold
  await rejects(input(c, 2, 2)); // wrong programme
  await rejects(input(c, 3, 2)); // wrong degree
  await rejects(input(c, 4, 1)); // issuer-only requires param 0
  await rejects(input(c, 5, 0)); // unknown claim type
});

test('signature by a different key is rejected', async () => {
  const other = credential({ signer: randomBytes(32) });
  await rejects(input(other, 1, 300));
});

test('commitment must match private fields', async () => {
  const c = credential();
  await rejects(input(c, 1, 300, { cgpa_x100: '400' })); // tampered private value
  await rejects(input(c, 1, 300, { salt: '1' }));
});

test('verification fails if public signals are altered', async () => {
  const c = credential();
  const { proof, publicSignals } = await prove(input(c, 1, 300));
  for (const [idx, val] of [[3, '1'], [4, '2'], [5, '200'], [0, '5'], [1, '5']]) {
    const bad = [...publicSignals];
    bad[idx] = val;
    assert.equal(await snarkjs.groth16.verify(VKEY, bad, proof), false, `signal ${idx}`);
  }
});

test('proof does not verify under another key (verification key mismatch)', async () => {
  const c = credential();
  const { proof, publicSignals } = await prove(input(c, 1, 300));
  const wrong = JSON.parse(JSON.stringify(VKEY));
  wrong.IC[1] = wrong.IC[2];
  assert.equal(await snarkjs.groth16.verify(wrong, publicSignals, proof), false);
});

test.after(() => setTimeout(() => process.exit(0), 100));
