<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\Payment\GatewayTimeoutException;
use App\Services\Payment\MockPaymentProvider;
use App\Services\PayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes a single instructor payout within a batch using the two-phase
 * commit pattern (pessimistic lock → withhold → gateway → settle/release/uncertain).
 *
 * Idempotency:
 *   Payout::firstOrCreate(['idempotency_key' => ...]) ensures exactly one payout
 *   row per instructor per batch, even if the job is retried after a crash.
 *
 * Concurrency Layers handled here:
 *   Layer 3 — Row-level pessimistic lock on the instructor record.
 *   Layer 4 — payout_withhold ledger entry zeroes the available balance.
 *   Layer 5 — Gateway call made OUTSIDE the database transaction.
 *   Layer 6 — Unique DB constraint on idempotency_key as a final guard.
 */
class ProcessInstructorPayoutJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of queue retries before the job is failed.
     */
    public int $tries = 1;

    public function __construct(
        private readonly User $instructor,
        private readonly PayoutBatch $batch,
    ) {}

    public function handle(PayoutService $payoutService, MockPaymentProvider $gateway): void
    {
        $payoutService->processInstructorPayout(
            instructor: $this->instructor,
            batch: $this->batch,
            gatewayCall: function (string $idempotencyKey, int $amountCents) use ($gateway): array {
                try {
                    $result = $gateway->executePayout($idempotencyKey, $amountCents);

                    return [
                        'status' => $result->status,
                        'provider_tx_id' => $result->providerTxId,
                        'message' => $result->message,
                    ];
                } catch (GatewayTimeoutException) {
                    return [
                        'status' => 'uncertain',
                        'provider_tx_id' => null,
                        'message' => null,
                    ];
                }
            },
        );
    }

    /**
     * Handle a job failure after all retries are exhausted.
     * Sets the payout status to failed if it is still in a non-terminal state.
     */
    public function failed(\Throwable $exception): void
    {
        $payout = Payout::where('idempotency_key', "{$this->batch->batch_uuid}_{$this->instructor->id}")
            ->first();

        if ($payout && ! $payout->status->isTerminal()) {
            $payout->update(['status' => PayoutStatus::Failed->value, 'failure_reason' => $exception->getMessage()]);
        }
    }
}
