#!/bin/sh
# Compile the circuit and run a DEVELOPMENT trusted setup (single-party). Not suitable for production:
# for production run a multi-party powers-of-tau ceremony and replace zkp/build/pot*.ptau.
# Usage: sh zkp/build.sh [circuit=credential] [version=1]   e.g. sh zkp/build.sh claimset 1   (from project root, inside the app container)
set -e
NAME=${1:-credential}
VER=${2:-1}
POT=14   # 2^14 constraints capacity
OUT=zkp/build
SNARKJS="node node_modules/snarkjs/build/cli.cjs"
mkdir -p $OUT public/zkp zkp/verification-keys

circom zkp/circuits/$NAME.circom --r1cs --wasm --sym -l node_modules -o $OUT
$SNARKJS r1cs info $OUT/$NAME.r1cs

if [ ! -f $OUT/pot${POT}_final.ptau ]; then
  $SNARKJS powersoftau new bn128 $POT $OUT/pot${POT}_0.ptau
  $SNARKJS powersoftau contribute $OUT/pot${POT}_0.ptau $OUT/pot${POT}_1.ptau --name="academiq-dev" -e="$(head -c 64 /dev/urandom | base64)"
  $SNARKJS powersoftau prepare phase2 $OUT/pot${POT}_1.ptau $OUT/pot${POT}_final.ptau
fi

$SNARKJS groth16 setup $OUT/$NAME.r1cs $OUT/pot${POT}_final.ptau $OUT/${NAME}_0.zkey
$SNARKJS zkey contribute $OUT/${NAME}_0.zkey $OUT/${NAME}_final.zkey --name="academiq-dev-phase2" -e="$(head -c 64 /dev/urandom | base64)"
$SNARKJS zkey export verificationkey $OUT/${NAME}_final.zkey zkp/verification-keys/${NAME}_v${VER}.vkey.json

# Proving artifacts are public by design (they contain no secrets); the browser downloads them.
cp $OUT/${NAME}_js/$NAME.wasm public/zkp/${NAME}_v${VER}.wasm
cp $OUT/${NAME}_final.zkey public/zkp/${NAME}_v${VER}.zkey
echo "Built ${NAME} v${VER}. Register the verification key with: php artisan zkp:register ${NAME} ${VER}"
