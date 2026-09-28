<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case FinanceAdmin = 'finance_admin';
    case Ops = 'ops';
    case Instructor = 'instructor';
    case Student = 'student';

    /**
     * Human label for Filament / API resources.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::FinanceAdmin => 'Finance Admin',
            self::Ops => 'Operations',
            self::Instructor => 'Instructor',
            self::Student => 'Student',
        };
    }
}
