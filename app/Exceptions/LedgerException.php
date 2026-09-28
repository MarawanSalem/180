<?php

declare(strict_types=1);

namespace App\Exceptions;

final class LedgerException extends DomainException
{
    /**
     * The idempotency key already exists in the ledger (duplicate entry attempt).
     */
    public static function duplicateIdempotencyKey(string $key): self
    {
        return new self(
            "A ledger entry with idempotency key '{$key}' already exists.",
            409,
            'ledger.duplicate_idempotency_key',
        );
    }

    /**
     * An attempt was made to mutate an immutable ledger entry.
     */
    public static function immutableRecord(): self
    {
        return new self(
            'Ledger entries are immutable and cannot be updated or deleted.',
            422,
            'ledger.immutable_record',
        );
    }

    /**
     * No payout_withhold entry exists to convert to payout_settled.
     */
    public static function withholdNotFound(int $payoutId): self
    {
        return new self(
            "No payout_withhold ledger entry found for payout #{$payoutId}.",
            404,
            'ledger.withhold_not_found',
        );
    }
}
