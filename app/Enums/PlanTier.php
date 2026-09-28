<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanTier: string
{
    case Monthly = 'monthly';
    case ThreeMonth = 'three_month';
    case Annual = 'annual';

    /**
     * The number of recognition periods this plan tier produces.
     */
    public function periodCount(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::ThreeMonth => 3,
            self::Annual => 12,
        };
    }
}
