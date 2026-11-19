// Circuit "claimset" v1. Needs: sh zkp/build.sh claimset 1
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import * as snarkjs from 'snarkjs';
import { buildEddsa, buildPoseidon } from 'circomlibjs';

const WASM = 'public/zkp/claimset_v1.wasm';
const ZKEY = 'public/zkp/claimset_v1.zkey';
const VKEY = JSON.parse(readFileSync('zkp/verification-keys/claimset_v1.vkey.json', 'utf8'));
const eddsa = await buildEddsa();
const poseidon = await buildPoseidon();
const F = eddsa.F;
const P = (a) => poseidon.F.toString(poseidon(a.map(BigInt)));

const priv = randomBytes(32);
const pub = eddsa.prv2pub(priv).map((x) => F.toString(x));

function credential({ cgpa = 352, programme = 1, degree = 1, inst = 1, year = 2025, signer = priv } = {}) {
  const fields = [11n, 22n, BigInt(cgpa), BigInt(programme), BigInt(degree), 20260601n, 1n, 987654321n];
  const c8 = P(fields);
  const c10 = P([c8, inst, year]);
  const sig = eddsa.signPoseidon(signer, F.e(BigInt(c10)));
  return { fields, inst, year, c10, R8: sig.R8.map((x) => F.toString(x)), S: sig.S.toString() };
}
const NONE = { sel_cgpa: 0, sel_year: 0, sel_inst: 0, sel_deg: 0, sel_prog: 0, rev_cgpa: 0, year_lo: 0, year_hi: 0, inst_expected: 0, deg_expected: 0, prog_expected: 0 };
function input(c, pubs = {}, extra = {}) {
  const [credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt] = c.fields.map(String);
  const p = { ...NONE, ...pubs };
  return { credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt,
    institution_code: String(c.inst), passing_year: String(c.year), R8x: c.R8[0], R8y: c.R8[1], S: c.S,
    commitment_v2: c.c10, Ax: pub[0], Ay: pub[1], bind: '777', ...Object.fromEntries(Object.entries(p).map(([k, v]) => [k, String(v)])), ...extra };
}
const prove = (i) => snarkjs.groth16.fullProve(i, WASM, ZKEY);

test('flags: ladder, year range, equalities, no reveal', async () => {
  const c = credential(); // cgpa 3.52, year 2025, KUET(1), BSc(1), CSE(1)
  const { proof, publicSignals: s } = await prove(input(c, { sel_cgpa: 1, sel_year: 1, year_lo: 2024, year_hi: 2026, sel_inst: 1, inst_expected: 1, sel_deg: 1, deg_expected: 1, sel_prog: 1, prog_expected: 2 }));
  assert.equal(s.length, 27);
  assert.deepEqual(s.slice(0, 7), ['1', '1', '1', '1', '1', '0', '0']); // 3.52: >=2.0 .. >=3.5, not 3.75/4.0
  assert.deepEqual(s.slice(7, 12), ['1', '1', '1', '0', '0']); // year ok, inst ok, deg ok, prog mismatch, cgpa not revealed
  assert.equal(s[12], c.c10);
  assert.equal(s[15], '777');
  assert.equal(await snarkjs.groth16.verify(VKEY, s, proof), true);
});

test('unselected claims give zero flags and cannot carry parameters', async () => {
  const c = credential();
  const { publicSignals: s } = await prove(input(c, { sel_inst: 1, inst_expected: 1 }));
  assert.deepEqual(s.slice(0, 8), ['0', '0', '0', '0', '0', '0', '0', '0']);
  assert.equal(s[8], '1');
  await assert.rejects(prove(input(c, { year_lo: 2020 }))); // parameter without selection
  await assert.rejects(prove(input(c, { rev_cgpa: 1 }))); // reveal without selection
});

test('reveal outputs the exact cgpa only when chosen', async () => {
  const { publicSignals: s } = await prove(input(credential(), { sel_cgpa: 1, rev_cgpa: 1 }));
  assert.equal(s[11], '352');
});

test('year outside the range yields flag 0 (honest FAIL), still a valid proof', async () => {
  const c = credential({ year: 2020 });
  const { proof, publicSignals: s } = await prove(input(c, { sel_year: 1, year_lo: 2024, year_hi: 2026 }));
  assert.equal(s[7], '0');
  assert.equal(await snarkjs.groth16.verify(VKEY, s, proof), true);
});

test('wrong signer, tampered private value, bad selector bit are rejected', async () => {
  await assert.rejects(prove(input(credential({ signer: randomBytes(32) }), { sel_cgpa: 1 })));
  await assert.rejects(prove(input(credential(), { sel_cgpa: 1 }, { cgpa_x100: '400' })));
  await assert.rejects(prove(input(credential(), { sel_cgpa: 1 }, { passing_year: '2000' }))); // year is bound by the signed commitment
  await assert.rejects(prove(input(credential(), { sel_cgpa: 2 })));
});

test('verification fails if public signals are altered (flags, selectors, bind)', async () => {
  const { proof, publicSignals } = await prove(input(credential(), { sel_cgpa: 1 }));
  for (const [idx, val] of [[0, '0'], [5, '1'], [11, '352'], [15, '778'], [16, '0'], [12, '5']]) {
    const bad = [...publicSignals];
    bad[idx] = val;
    assert.equal(await snarkjs.groth16.verify(VKEY, bad, proof), false, `signal ${idx}`);
  }
});

test.after(() => setTimeout(() => process.exit(0), 100));
