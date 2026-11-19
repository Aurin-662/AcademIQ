<?php

namespace App\Services\Verification;

use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\EmployerProfile;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Models\VerificationKey;
use App\Models\VerificationToken;
use App\Services\AuditLogger;
use App\Services\ZKP\Groth16Verifier;
use App\Services\ZKP\TokenService;

/**
 * Phase 3: verifier-side check of a claim-set token against selectable criteria.
 * check() runs, in order: token usable (exists, active, unexpired) -> credential current (not revoked/superseded)
 * -> issuer key valid -> Groth16 verify of the stored proof -> criteria evaluated on the verified public flags.
 * Every call writes a TokenUsageLog row (outcomes only) and an audit log entry. Hidden values are never available here.
 */
class TokenVerifier
{
    public const PASS = 'PASS', FAIL = 'FAIL', NOT_PROVEN = 'NOT PROVEN';
    public const TOKEN_RE = '/ACQ(?:-[0-9A-Z]{4}){4}/';

    // publicSignals indexes (zkp/circuits/claimset.circom)
    private const S = ['cgpa' => 16, 'year' => 17, 'institution' => 18, 'degree' => 19, 'department' => 20];
    private const FLAG = ['year' => 7, 'institution' => 8, 'degree' => 9, 'department' => 10];
    private const EXPECTED = ['institution' => 24, 'degree' => 25, 'department' => 26];
    private const REVEAL = 21, REVEALED = 11, YEAR_LO = 22, YEAR_HI = 23;

    public function __construct(private Groth16Verifier $verifier) {}

    /** Pulls a token id out of typed text, a pasted link, or a decoded QR payload. */
    public static function extract(string $text): ?string
    {
        return preg_match(self::TOKEN_RE, strtoupper($text), $m) ? $m[0] : null;
    }

    /**
     * Normalised criteria list from raw form values. Each criterion is optional; at least one is required by callers.
     * @param array $in cgpa_min, year_from, year_to, institution, degree, department
     * @return array<int,array{key:string,label:string,value:mixed}>
     */
    public static function criteria(array $in): array
    {
        $c = [];
        if (($in['cgpa_min'] ?? '') !== '') {
            $c[] = ['key' => 'cgpa', 'label' => 'CGPA at least '.number_format((float) $in['cgpa_min'], 2), 'value' => (int) round((float) $in['cgpa_min'] * 100)];
        }
        $from = ($in['year_from'] ?? '') !== '' ? (int) $in['year_from'] : null;
        $to = ($in['year_to'] ?? '') !== '' ? (int) $in['year_to'] : null;
        if ($from !== null || $to !== null) {
            $a = $from ?? config('academiq.min_passing_year');
            $b = $to ?? (int) now()->year;
            $c[] = ['key' => 'year', 'label' => $a === $b ? "Passing year is $a" : ($to === null ? "Passing year $a or later" : ($from === null ? "Passing year $b or earlier" : "Passing year between $a and $b")), 'value' => [$a, $b]];
        }
        foreach (['institution' => 'institutions', 'degree' => 'degrees', 'department' => 'programmes'] as $k => $table) {
            if (($in[$k] ?? '') !== '') {
                $c[] = ['key' => $k, 'label' => ucfirst($k).' is '.(config("academiq.$table")[(int) $in[$k]] ?? '?'), 'value' => (int) $in[$k]];
            }
        }

        return $c;
    }

    /**
     * Pure evaluation of criteria against the 27 verified public signals.
     * PASS/FAIL only when the proven flags settle the question; otherwise NOT PROVEN.
     * @return array<int,array{key:string,label:string,result:string}>
     */
    public static function evaluate(array $s, array $criteria): array
    {
        $s = array_map('strval', $s);
        $out = [];
        foreach ($criteria as $c) {
            $out[] = ['key' => $c['key'], 'label' => $c['label'], 'result' => self::one($s, $c['key'], $c['value'])];
        }

        return $out;
    }

    /** Combine with AND: any FAIL -> FAIL, all PASS -> PASS, otherwise NOT PROVEN. */
    public static function overall(array $results): string
    {
        $r = array_column($results, 'result');
        if (in_array(self::FAIL, $r, true)) {
            return self::FAIL;
        }

        return $r && count(array_unique($r)) === 1 && $r[0] === self::PASS ? self::PASS : self::NOT_PROVEN;
    }

    private static function one(array $s, string $key, mixed $v): string
    {
        if ($s[self::S[$key]] !== '1') {
            return self::NOT_PROVEN; // holder did not include this claim
        }
        switch ($key) {
            case 'cgpa':
                if ($s[self::REVEAL] === '1') {
                    return (int) $s[self::REVEALED] >= $v ? self::PASS : self::FAIL;
                }
                $i = array_search($v, TokenService::LADDER, true);

                return $i === false ? self::NOT_PROVEN : ($s[$i] === '1' ? self::PASS : self::FAIL);

            case 'year': // proven: year in [lo,hi] when flag=1, outside it when flag=0. Criterion: year in [a,b].
                [$a, $b] = $v;
                $lo = (int) $s[self::YEAR_LO];
                $hi = (int) $s[self::YEAR_HI];
                if ($s[self::FLAG['year']] === '1') {
                    return $lo >= $a && $hi <= $b ? self::PASS : ($hi < $a || $lo > $b ? self::FAIL : self::NOT_PROVEN);
                }

                return $lo <= $a && $hi >= $b ? self::FAIL : self::NOT_PROVEN;

            default: // equality claims: proven "value == expected" (flag=1) or "!= expected" (flag=0)
                $expected = (int) $s[self::EXPECTED[$key]];
                $flag = $s[self::FLAG[$key]] === '1';
                if ($expected === $v) {
                    return $flag ? self::PASS : self::FAIL;
                }

                return $flag ? self::FAIL : self::NOT_PROVEN;
        }
    }

    /**
     * Full check + audit. $meta may carry batch_id (Phase 4).
     * @return array{valid:bool,reason:?string,results:array,overall:string,claims:array,check_id:string}
     */
    public function check(User $verifier, string $tokenId, array $criteria, array $meta = []): array
    {
        $r = $this->run($tokenId, $criteria);
        $name = EmployerProfile::where('user_id', (string) $verifier->getKey())->value('company_name') ?: $verifier->name;
        $log = TokenUsageLog::create([
            'token_id' => $tokenId, 'verifier_id' => (string) $verifier->getKey(), 'verifier_name' => $name, 'batch_id' => $meta['batch_id'] ?? null,
            'valid' => $r['valid'], 'reason' => $r['reason'], 'criteria' => $criteria, 'results' => $r['results'], 'overall' => $r['overall'],
            'revealed_cgpa' => $r['revealed_cgpa'] ?? null,
        ]);
        AuditLogger::log($r['valid'] ? 'token.verified' : 'token.verify_failed', 'verification_token', $tokenId,
            ['overall' => $r['overall'], 'reason' => $r['reason'], 'criteria' => array_column($criteria, 'key'), 'batch_id' => $meta['batch_id'] ?? null], $verifier);

        return $r + ['check_id' => (string) $log->getKey()];
    }

    /** @return array{valid:bool,reason:?string,results:array,overall:string,claims:array,revealed_cgpa?:?float} */
    private function run(string $tokenId, array $criteria): array
    {
        $bad = fn (string $reason, array $claims = []) => ['valid' => false, 'reason' => $reason, 'results' => [], 'overall' => 'INVALID', 'claims' => $claims];

        $t = preg_match('/^'.trim(self::TOKEN_RE, '/').'$/', $tokenId) ? VerificationToken::where('token_id', $tokenId)->first() : null;
        if (! $t || ! in_array($t->status, ['active', 'revoked'], true)) {
            return $bad('unknown_token');
        }
        if ($t->status === 'revoked') {
            return $bad('revoked', $t->claims);
        }
        if (! $t->expires_at || $t->expires_at->isPast()) {
            return $bad('expired', $t->claims);
        }
        $cred = Credential::where('credential_id', $t->credential_id)->first();
        if (! $cred || $cred->credential_status !== 'active') {
            return $bad('credential_revoked', $t->claims);
        }
        if ($cred->commitment_v2 !== $t->commitment_v2) {
            return $bad('credential_superseded', $t->claims);
        }
        $s = array_map('strval', $t->public_signals);
        $issuer = AuthorizedIssuer::where('issuer_id', $cred->issuer_id)->first();
        $key = $issuer ? collect($issuer->keys)->firstWhere('key_id', $cred->issuer_key_id) : null;
        if (! $issuer || $issuer->authorization_status !== 'active' || ! $key || $key['status'] === 'revoked' || $s[13] !== $key['ax'] || $s[14] !== $key['ay'] || $s[12] !== $cred->commitment_v2) {
            return $bad('issuer_not_authorized', $t->claims);
        }
        $vk = VerificationKey::where('circuit_id', TokenService::CIRCUIT)->where('circuit_version', (int) $t->circuit_version)->where('status', 'active')->first();
        if (! $vk || ! $this->verifier->verify($vk->verification_key, $t->proof, $s)) {
            return $bad('invalid_proof', $t->claims);
        }
        $results = self::evaluate($s, $criteria);

        return ['valid' => true, 'reason' => null, 'results' => $results, 'overall' => $criteria ? self::overall($results) : self::NOT_PROVEN, 'claims' => $t->claims,
            'revealed_cgpa' => $s[self::REVEAL] === '1' && $s[self::S['cgpa']] === '1' ? (int) $s[self::REVEALED] / 100 : null];
    }

    /** Human text for an invalid reason. */
    public static function reasonText(?string $reason): string
    {
        return match ($reason) {
            'unknown_token' => 'Token not found.', 'revoked' => 'Token was revoked by the holder.', 'expired' => 'Token has expired.',
            'credential_revoked' => 'The underlying credential was revoked.', 'credential_superseded' => 'The credential has a newer version; this token is out of date.',
            'issuer_not_authorized' => 'Issuer is no longer authorized.', 'invalid_proof' => 'Cryptographic proof did not verify.', default => '',
        };
    }
}
