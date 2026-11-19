pragma circom 2.1.0;

include "circomlib/circuits/poseidon.circom";
include "circomlib/circuits/eddsaposeidon.circom";
include "circomlib/circuits/comparators.circom";
include "circomlib/circuits/bitify.circom";

/*
 * AcademiQ claim-set proof, circuit id "claimset", version 1.
 *
 * One proof carries a SET of claims chosen by the holder before any verifier is known. The verifier later evaluates
 * its own criteria against the verified public flags.
 *
 * Commitment scheme (issuer signs two commitments; commitment v1 is unchanged and still used by circuit "credential"):
 *   c8  = Poseidon(8)[credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt]
 *   c10 = Poseidon(3)[c8, institution_code, passing_year]        <- public `commitment_v2`, EdDSA-signed by the issuer
 *
 * Proves: holder knows the preimage of commitment_v2 and it is signed by (Ax, Ay); and computes these flags.
 * A claim the holder did not select (sel_* = 0) has all its public parameters forced to 0 and its flags forced to 0;
 * the verifier reads sel_* to tell NOT PROVEN apart from FAIL.
 *
 * Public signals (snarkjs order: outputs first, then public inputs in declaration order):
 *   0..6  cgpa_ge[i]   = sel_cgpa * (cgpa_x100 >= [200,250,300,325,350,375,400][i])
 *   7     year_ok      = sel_year * (year_lo <= passing_year <= year_hi)
 *   8     inst_ok      = sel_inst * (institution_code == inst_expected)
 *   9     deg_ok       = sel_deg  * (degree_category  == deg_expected)
 *   10    prog_ok      = sel_prog * (programme_code   == prog_expected)
 *   11    cgpa_revealed= rev_cgpa * cgpa_x100             (0 unless the holder chose to reveal)
 *   12 commitment_v2, 13 Ax, 14 Ay, 15 bind (field-encoded token id), 16 sel_cgpa, 17 sel_year, 18 sel_inst, 19 sel_deg,
 *   20 sel_prog, 21 rev_cgpa, 22 year_lo, 23 year_hi, 24 inst_expected, 25 deg_expected, 26 prog_expected
 */
template ClaimSet() {
    // ---- private ----
    signal input credential_id;
    signal input student_reference;
    signal input cgpa_x100;
    signal input programme_code;
    signal input degree_category;
    signal input issue_date;
    signal input credential_version;
    signal input salt;
    signal input institution_code;
    signal input passing_year;
    signal input R8x;
    signal input R8y;
    signal input S;

    // ---- public inputs (order matters) ----
    signal input commitment_v2;
    signal input Ax;
    signal input Ay;
    signal input bind;
    signal input sel_cgpa;
    signal input sel_year;
    signal input sel_inst;
    signal input sel_deg;
    signal input sel_prog;
    signal input rev_cgpa;
    signal input year_lo;
    signal input year_hi;
    signal input inst_expected;
    signal input deg_expected;
    signal input prog_expected;

    // ---- public outputs ----
    signal output cgpa_ge[7];
    signal output year_ok;
    signal output inst_ok;
    signal output deg_ok;
    signal output prog_ok;
    signal output cgpa_revealed;

    // commitments
    component h8 = Poseidon(8);
    h8.inputs[0] <== credential_id;
    h8.inputs[1] <== student_reference;
    h8.inputs[2] <== cgpa_x100;
    h8.inputs[3] <== programme_code;
    h8.inputs[4] <== degree_category;
    h8.inputs[5] <== issue_date;
    h8.inputs[6] <== credential_version;
    h8.inputs[7] <== salt;
    component h3 = Poseidon(3);
    h3.inputs[0] <== h8.out;
    h3.inputs[1] <== institution_code;
    h3.inputs[2] <== passing_year;
    h3.out === commitment_v2;

    // issuer signature over commitment_v2
    component sig = EdDSAPoseidonVerifier();
    sig.enabled <== 1;
    sig.Ax <== Ax;
    sig.Ay <== Ay;
    sig.R8x <== R8x;
    sig.R8y <== R8y;
    sig.S <== S;
    sig.M <== commitment_v2;

    // selectors are bits; reveal only a selected claim
    sel_cgpa * (sel_cgpa - 1) === 0;
    sel_year * (sel_year - 1) === 0;
    sel_inst * (sel_inst - 1) === 0;
    sel_deg * (sel_deg - 1) === 0;
    sel_prog * (sel_prog - 1) === 0;
    rev_cgpa * (rev_cgpa - 1) === 0;
    rev_cgpa * (1 - sel_cgpa) === 0;

    // unselected claims carry no parameters
    (1 - sel_year) * year_lo === 0;
    (1 - sel_year) * year_hi === 0;
    (1 - sel_inst) * inst_expected === 0;
    (1 - sel_deg) * deg_expected === 0;
    (1 - sel_prog) * prog_expected === 0;

    // range checks keep the comparators sound
    component cgpaBits = Num2Bits(16); cgpaBits.in <== cgpa_x100;
    component yearBits = Num2Bits(16); yearBits.in <== passing_year;
    component loBits = Num2Bits(16); loBits.in <== year_lo;
    component hiBits = Num2Bits(16); hiBits.in <== year_hi;

    // CGPA ladder
    var th[7] = [200, 250, 300, 325, 350, 375, 400];
    component ge[7];
    for (var i = 0; i < 7; i++) {
        ge[i] = GreaterEqThan(16);
        ge[i].in[0] <== cgpa_x100;
        ge[i].in[1] <== th[i];
        cgpa_ge[i] <== ge[i].out * sel_cgpa;
    }
    cgpa_revealed <== rev_cgpa * cgpa_x100;

    // passing year in [year_lo, year_hi]
    component loOk = LessEqThan(16); loOk.in[0] <== year_lo; loOk.in[1] <== passing_year;
    component hiOk = LessEqThan(16); hiOk.in[0] <== passing_year; hiOk.in[1] <== year_hi;
    signal inRange;
    inRange <== loOk.out * hiOk.out;
    year_ok <== inRange * sel_year;

    // equality flags
    component eqI = IsEqual(); eqI.in[0] <== institution_code; eqI.in[1] <== inst_expected;
    component eqD = IsEqual(); eqD.in[0] <== degree_category; eqD.in[1] <== deg_expected;
    component eqP = IsEqual(); eqP.in[0] <== programme_code; eqP.in[1] <== prog_expected;
    inst_ok <== eqI.out * sel_inst;
    deg_ok <== eqD.out * sel_deg;
    prog_ok <== eqP.out * sel_prog;

    // bind the proof to one token id (squaring keeps the public input in the constraint system)
    signal bind_sq;
    bind_sq <== bind * bind;
}

component main { public [commitment_v2, Ax, Ay, bind, sel_cgpa, sel_year, sel_inst, sel_deg, sel_prog, rev_cgpa, year_lo, year_hi, inst_expected, deg_expected, prog_expected] } = ClaimSet();
