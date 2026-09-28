<?php

declare(strict_types=1);

namespace App\Exceptions;

final class SubscriptionException extends DomainException
{
    /**
     * The subscription was not found.
     */
    public static function notFound(int $id): self
    {
        return new self(
            "Subscription #{$id} was not found.",
            404,
            'subscription.not_found',
        );
    }

    /**
     * The subscription is not in a state that allows upgrading.
     */
    public static function notUpgradeable(int $id): self
    {
        return new self(
            "Subscription #{$id} cannot be upgraded in its current state.",
            422,
            'subscription.not_upgradeable',
        );
    }

    /**
     * The new plan tier is not a valid upgrade from the current tier.
     */
    public static function invalidUpgradePath(string $from, string $to): self
    {
        return new self(
            "Cannot upgrade from plan '{$from}' to plan '{$to}'.",
            422,
            'subscription.invalid_upgrade_path',
        );
    }

    /**
     * The period has already been distributed and cannot be re-processed.
     */
    public static function periodAlreadyDistributed(int $periodId): self
    {
        return new self(
            "Subscription period #{$periodId} has already been distributed.",
            409,
            'subscription.period_already_distributed',
        );
    }

    /**
     * The penny-perfect invariant was violated during revenue allocation.
     */
    public static function pennyInvariantViolated(int $expected, int $actual): self
    {
        return new self(
            "Revenue allocation invariant violated: expected {$expected} cents but allocated {$actual} cents.",
            500,
            'subscription.penny_invariant_violated',
        );
    }
}
