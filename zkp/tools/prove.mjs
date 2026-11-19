// DEV/TEST ONLY: proof generation outside a browser (the real prover runs in the student's browser).
// stdin: {input, wasm, zkey}; stdout: {proof, publicSignals}.
import * as snarkjs from 'snarkjs';

let s = '';
for await (const c of process.stdin) s += c;
const { input, wasm, zkey } = JSON.parse(s);
try {
  const { proof, publicSignals } = await snarkjs.groth16.fullProve(input, wasm, zkey);
  process.stdout.write(JSON.stringify({ proof, publicSignals }), () => process.exit(0));
} catch (e) {
  process.stderr.write('witness/proof failed: ' + e.message.split('\n')[0]);
  process.exit(3);
}
