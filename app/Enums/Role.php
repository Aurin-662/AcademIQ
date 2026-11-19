<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Issuer = 'issuer';
    case Student = 'student';
    case Employer = 'employer';
    case PreviousEmployer = 'previous_employer';
    case Referee = 'referee';
    case PoliceVerifier = 'police_verifier';

    /** Roles anyone may self-register as. */
    public static function selfRegistrable(): array
    {
        return [self::Student->value, self::Employer->value];
    }

    public function isExternal(): bool
    {
        return in_array($this, [self::PreviousEmployer, self::Referee, self::PoliceVerifier], true);
    }

    public function home(): string
    {
        return match (true) {
            $this === self::Admin, $this === self::Issuer => route('admin.dashboard'),
            $this === self::Student => route('student.dashboard'),
            $this === self::Employer => route('employer.dashboard'),
            default => route('external.dashboard'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'KUET Admin',
            self::Issuer => 'Authorized Issuer',
            self::Student => 'Student',
            self::Employer => 'Employer',
            self::PreviousEmployer => 'Previous Employer',
            self::Referee => 'Referee',
            self::PoliceVerifier => 'Police Verifier',
        };
    }
}
