<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Jobs\ReconcileUncertainPayoutsJob;
use RuntimeException;

/**
 * Thrown when the payment gateway connection drops or times out before
 * returning a definitive success or failure response.
 *
 * Catching this exception should set payout status to UNCERTAIN and leave
 * the payout_withhold ledger entry intact for async reconciliation.
 *
 * @see ReconcileUncertainPayoutsJob
 */
final class GatewayTimeoutException extends RuntimeException
{
    public function __construct(string $idempotencyKey, int $amountCents)
    {
        parent::__construct(
            "Gateway timed out for idempotency_key={$idempotencyKey}, amount={$amountCents} cents. "
            .'Payout status set to UNCERTAIN pending async reconciliation.',
        );
    }
}
