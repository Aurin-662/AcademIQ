<?php

namespace App\Services\Verification;

/** Plain-language descriptions shown on the consent screen and employer views. */
class ClaimDescriber
{
    public static function academic(array $claim): string
    {
        return match ((int) $claim['claim_type']) {
            1 => 'CGPA is at least '.number_format($claim['param'] / 100, 2),
            2 => 'Programme is '.(config('academiq.programmes')[$claim['param']] ?? 'unknown'),
            3 => 'Degree is '.(config('academiq.degrees')[$claim['param']] ?? 'unknown'),
            4 => 'Credential was issued by an authorized KUET issuer and is currently valid',
            default => 'Unknown claim',
        };
    }

    public static function academicShared(): string
    {
        return 'Only whether this statement is true. Your actual CGPA, programme, degree, signature and other credential data are not shared.';
    }

    public static function background(string $type, array $details): array
    {
        return match ($type) {
            'police' => ['Police verification', 'Your name and student reference are sent to the selected police verification participant, who responds with a verification status. Lawful basis stated by the employer: '.($details['lawful_basis'] ?? '-')],
            'reference' => ['Reference check', 'Your name and the position applied for ('.($details['position_applied'] ?? '-').') are sent to the selected referee, who submits a reference response.'],
            'employment' => ['Work experience verification', 'Your name and the employment details claimed ('.($details['company'] ?? '-').', '.($details['job_title'] ?? '-').', '.($details['period'] ?? '-').') are sent to the selected previous employer, who confirms or rejects them.'],
            default => [$type, ''],
        };
    }

    public static function backgroundLabel(string $type): string
    {
        return ['police' => 'Police verification', 'reference' => 'Reference check', 'employment' => 'Work experience'][$type] ?? $type;
    }
}
