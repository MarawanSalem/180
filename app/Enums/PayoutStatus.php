<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Uncertain = 'uncertain';

    /**
     * Whether this status is terminal (no further processing will occur).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Failed => true,
            default => false,
        };
    }
}
