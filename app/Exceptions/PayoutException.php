<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PayoutException extends DomainException
{
    /**
     * The instructor's balance is below the minimum payout threshold (2,000 cents).
     */
    public static function insufficientBalance(int $balanceCents, int $thresholdCents): self
    {
        return new self(
            "Instructor balance of {$balanceCents} cents is below the required threshold of {$thresholdCents} cents.",
            422,
            'payout.insufficient_balance',
        );
    }

    /**
     * A payout is already in a terminal state and cannot be retried.
     */
    public static function alreadyTerminal(int $payoutId): self
    {
        return new self(
            "Payout #{$payoutId} is in a terminal state and cannot be processed again.",
            409,
            'payout.already_terminal',
        );
    }

    /**
     * Concurrent payout dispatch detected for the same instructor in the same batch.
     */
    public static function duplicateDispatch(string $idempotencyKey): self
    {
        return new self(
            "A payout for idempotency key '{$idempotencyKey}' is already being processed.",
            409,
            'payout.duplicate_dispatch',
        );
    }

    /**
     * The payout batch was not found.
     */
    public static function batchNotFound(int $batchId): self
    {
        return new self(
            "Payout batch #{$batchId} was not found.",
            404,
            'payout.batch_not_found',
        );
    }

    /**
     * A payout cycle is already holding the distributed execution lock.
     */
    public static function cycleAlreadyRunning(): self
    {
        return new self(
            'A payout cycle is already running. Retry after the current cycle finishes.',
            409,
            'payout.cycle_already_running',
        );
    }
}
