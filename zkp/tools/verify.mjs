// Server-side Groth16 verification. stdin: {vkey, proof, publicSignals}; stdout: {valid}. Everything stays in memory.
import * as snarkjs from 'snarkjs';

let s = '';
for await (const c of process.stdin) s += c;
const { vkey, proof, publicSignals } = JSON.parse(s);
let valid = false;
try {
  valid = await snarkjs.groth16.verify(vkey, publicSignals, proof);
} catch {
  valid = false; // malformed proof or signals
}
process.stdout.write(JSON.stringify({ valid }), () => process.exit(0));
