pragma circom 2.1.0;

include "circomlib/circuits/poseidon.circom";
include "circomlib/circuits/eddsaposeidon.circom";
include "circomlib/circuits/comparators.circom";
include "circomlib/circuits/bitify.circom";

/*
 * AcademiQ credential proof, circuit id "credential", version 1.
 *
 * Proves, without revealing the credential:
 *   1. The holder knows a credential whose Poseidon(8) commitment equals the public `commitment`.
 *   2. That commitment is signed (EdDSA-Poseidon / BabyJubJub) by the public issuer key (Ax, Ay).
 *   3. One bounded claim about the hidden values holds, selected by `claim_type`:
 *        1: cgpa_x100 >= param
 *        2: programme_code == param
 *        3: degree_category == param
 *        4: issuer-only (no extra predicate; param must be 0)
 *   4. The proof is bound to `request_id` (field-encoded request id) so it cannot be replayed on another request.
 *
 * Commitment preimage order (must match app/Services/Credentials/FieldEncoder.php):
 *   [credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt]
 *
 * Public signal order (snarkjs publicSignals): [commitment, Ax, Ay, request_id, claim_type, param]
 */
template CredentialClaim() {
    // ---- private ----
    signal input credential_id;
    signal input student_reference;
    signal input cgpa_x100;
    signal input programme_code;
    signal input degree_category;
    signal input issue_date;
    signal input credential_version;
    signal input salt;
    signal input R8x;
    signal input R8y;
    signal input S;

    // ---- public (order matters) ----
    signal input commitment;
    signal input Ax;
    signal input Ay;
    signal input request_id;
    signal input claim_type;
    signal input param;

    // 1. commitment
    component h = Poseidon(8);
    h.inputs[0] <== credential_id;
    h.inputs[1] <== student_reference;
    h.inputs[2] <== cgpa_x100;
    h.inputs[3] <== programme_code;
    h.inputs[4] <== degree_category;
    h.inputs[5] <== issue_date;
    h.inputs[6] <== credential_version;
    h.inputs[7] <== salt;
    h.out === commitment;

    // 2. issuer signature over the commitment
    component sig = EdDSAPoseidonVerifier();
    sig.enabled <== 1;
    sig.Ax <== Ax;
    sig.Ay <== Ay;
    sig.R8x <== R8x;
    sig.R8y <== R8y;
    sig.S <== S;
    sig.M <== commitment;

    // 3. claim selection (exactly one of 1..4)
    component is1 = IsEqual(); is1.in[0] <== claim_type; is1.in[1] <== 1;
    component is2 = IsEqual(); is2.in[0] <== claim_type; is2.in[1] <== 2;
    component is3 = IsEqual(); is3.in[0] <== claim_type; is3.in[1] <== 3;
    component is4 = IsEqual(); is4.in[0] <== claim_type; is4.in[1] <== 4;
    is1.out + is2.out + is3.out + is4.out === 1;

    // range checks keep GreaterEqThan sound
    component cgpaBits = Num2Bits(16); cgpaBits.in <== cgpa_x100;
    component paramBits = Num2Bits(16); paramBits.in <== param;

    component ge = GreaterEqThan(16);
    ge.in[0] <== cgpa_x100;
    ge.in[1] <== param;
    is1.out * (1 - ge.out) === 0;

    is2.out * (programme_code - param) === 0;
    is3.out * (degree_category - param) === 0;
    is4.out * param === 0;

    // 4. bind request id (a squaring constraint keeps the public input in the constraint system)
    signal request_sq;
    request_sq <== request_id * request_id;
}

component main { public [commitment, Ax, Ay, request_id, claim_type, param] } = CredentialClaim();
