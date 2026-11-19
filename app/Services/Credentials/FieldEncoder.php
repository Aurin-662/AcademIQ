<?php

namespace App\Services\Credentials;

/**
 * Deterministic encoding into BN254 scalar-field integers (all values < 2^248, safely below the field modulus).
 * Credential field order (Poseidon(8) preimage):
 *   [credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date_yyyymmdd, credential_version, salt]
 */
class FieldEncoder
{
    /** sha256(utf8) truncated to its first 31 bytes, as a decimal string. */
    public static function fromString(string $s): string
    {
        return gmp_strval(gmp_import(substr(hash('sha256', $s, true), 0, 31)));
    }

    public static function randomSalt(): string
    {
        return gmp_strval(gmp_import(random_bytes(31)));
    }

    /** "3.52" -> 352 */
    public static function cgpaX100(string $cgpa): int
    {
        return (int) round(((float) $cgpa) * 100);
    }

    public static function date(string $ymd): int
    {
        return (int) str_replace('-', '', $ymd);
    }

    public static function fields(string $credentialId, string $studentRef, int $cgpa, int $programme, int $degree, int $date, int $version, string $salt): array
    {
        return [self::fromString($credentialId), self::fromString($studentRef), (string) $cgpa, (string) $programme, (string) $degree, (string) $date, (string) $version, $salt];
    }
}
