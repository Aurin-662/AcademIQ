// Server-side crypto helper. Reads one JSON command from stdin, writes one JSON result to stdout.
// Ops: keygen | pubkey | commit | sign | verify | seal | batch (runs many ops in one process: {cmds:[...]} -> {results:[...]})
import { buildEddsa, buildPoseidon } from 'circomlibjs';
import { randomBytes, webcrypto } from 'node:crypto';

const subtle = webcrypto.subtle;
const b64 = (u8) => Buffer.from(u8).toString('base64');
const unb64 = (s) => new Uint8Array(Buffer.from(s, 'base64'));

let stdin = '';
for await (const c of process.stdin) stdin += c;
const cmd = JSON.parse(stdin);

const eddsa = await buildEddsa();
const F = eddsa.F;
const poseidon = await buildPoseidon();
const priv = (hex) => Buffer.from(hex, 'hex');
const pubOf = (hex) => eddsa.prv2pub(priv(hex)).map((x) => F.toString(x));

const ops = {
  keygen() {
    const hex = randomBytes(32).toString('hex');
    return { priv_hex: hex, pub: pubOf(hex) };
  },
  pubkey: ({ priv_hex }) => ({ pub: pubOf(priv_hex) }),
  commit: ({ fields }) => ({ commitment: poseidon.F.toString(poseidon(fields.map(BigInt))) }),
  sign({ priv_hex, msg }) {
    const sig = eddsa.signPoseidon(priv(priv_hex), F.e(BigInt(msg)));
    return { R8: sig.R8.map((x) => F.toString(x)), S: sig.S.toString() };
  },
  verify({ pub, msg, R8, S }) {
    const sig = { R8: R8.map((x) => F.e(BigInt(x))), S: BigInt(S) };
    return { valid: eddsa.verifyPoseidon(F.e(BigInt(msg)), sig, pub.map((x) => F.e(BigInt(x)))) };
  },
  // ECIES to the wallet's P-256 ECDH public key: ephemeral ECDH -> HKDF-SHA256 -> AES-256-GCM.
  async seal({ recipient_pub_b64, plaintext }) {
    const rpub = await subtle.importKey('raw', unb64(recipient_pub_b64), { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    const eph = await subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
    const secret = await subtle.deriveBits({ name: 'ECDH', public: rpub }, eph.privateKey, 256);
    const hk = await subtle.importKey('raw', secret, 'HKDF', false, ['deriveKey']);
    const key = await subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: new TextEncoder().encode('academiq-delivery-v1') }, hk, { name: 'AES-GCM', length: 256 }, false, ['encrypt']);
    const iv = randomBytes(12);
    const ct = await subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(plaintext));
    return { epk: b64(new Uint8Array(await subtle.exportKey('raw', eph.publicKey))), iv: b64(iv), ct: b64(new Uint8Array(ct)) };
  },
  async batch({ cmds }) {
    const results = [];
    for (const c of cmds) results.push(await ops[c.op](c));
    return { results };
  },
};

if (!ops[cmd.op]) { console.error('unknown op'); process.exit(2); }
process.stdout.write(JSON.stringify(await ops[cmd.op](cmd)));
