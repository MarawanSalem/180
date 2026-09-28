<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerEntryType: string
{
    case Earning = 'earning';
    case PayoutWithhold = 'payout_withhold';
    case PayoutSettled = 'payout_settled';
    case Clawback = 'clawback';
    case Adjustment = 'adjustment';

    /**
     * Whether this entry type represents a credit (money flowing in to the instructor).
     */
    public function isCredit(): bool
    {
        return match ($this) {
            self::Earning, self::Adjustment => true,
            default => false,
        };
    }

    /**
     * Whether this entry type represents a debit (money flowing out of the instructor's balance).
     */
    public function isDebit(): bool
    {
        return match ($this) {
            self::PayoutWithhold, self::PayoutSettled, self::Clawback => true,
            default => false,
        };
    }
}
