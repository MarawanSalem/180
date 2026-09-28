<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Deterministic tri-state payment gateway simulator.
 *
 * Outcome (success / failure / timeout) is derived from a CRC32 hash of the
 * idempotency key. This makes every call with the same key repeatable, which
 * is essential for `verifyStatus()` to mirror `executePayout()` without
 * persisting any state.
 *
 * Probabilities are configured via environment variables:
 *   MOCK_GATEWAY_SUCCESS_RATE  (default: 80)  — % of keys that succeed
 *   MOCK_GATEWAY_FAILURE_RATE  (default: 10)  — % of keys that hard-fail
 *   MOCK_GATEWAY_TIMEOUT_RATE  (default: 10)  — % of keys that time out
 *
 * The three rates must sum to 100. The remainder after success + failure is
 * automatically assigned to timeout to prevent misconfiguration drift.
 *
 * Provider transaction IDs follow the pattern: `mock_tx_{crc32_hash}`.
 */
class MockPaymentProvider
{
    /**
     * Simulate executing a payout.
     *
     * Called OUTSIDE a database transaction (Phase 2 of the two-phase commit).
     * On timeout, throws GatewayTimeoutException instead of returning a result.
     *
     * @throws GatewayTimeoutException when the simulated outcome is timeout.
     */
    public function executePayout(string $idempotencyKey, int $amountCents): GatewayResult
    {
        $outcome = $this->resolveOutcome($idempotencyKey);

        if ($outcome === 'timeout') {
            throw new GatewayTimeoutException($idempotencyKey, $amountCents);
        }

        return $outcome === 'success'
            ? GatewayResult::success($this->buildProviderTxId($idempotencyKey))
            : GatewayResult::failed('Gateway rejected the payout request (simulated permanent failure).');
    }

    /**
     * Verify the status of a previously submitted payout for reconciliation.
     *
     * Uses the same deterministic hash as executePayout(), so a key that
     * originally resulted in timeout will resolve to either success or failed
     * on the next check — matching real gateway "check payment" semantics.
     *
     * Never throws GatewayTimeoutException; always returns a definitive result.
     */
    public function verifyStatus(string $idempotencyKey): GatewayResult
    {
        // Reconciliation uses the same key hash but interprets timeout as
        // "not found / never executed" — a definitive FAILED outcome.
        $outcome = $this->resolveOutcome($idempotencyKey);

        return $outcome === 'success'
            ? GatewayResult::success($this->buildProviderTxId($idempotencyKey))
            : GatewayResult::failed('Gateway has no record of this transaction (simulated not-found).');
    }

    /**
     * Determine the simulated outcome for a given idempotency key.
     * Returns 'success', 'failed', or 'timeout'.
     *
     * The CRC32 hash is taken modulo 100 to produce a 0–99 bucket.
     * Buckets 0–(successRate-1)       → success
     * Buckets successRate–(s+f-1)     → failed
     * Buckets (s+f)–99                → timeout
     */
    private function resolveOutcome(string $idempotencyKey): string
    {
        $successRate = $this->successRate();
        $failureRate = $this->failureRate();

        // Use the absolute value of CRC32 to ensure a positive bucket integer.
        $bucket = abs(crc32($idempotencyKey)) % 100;

        if ($bucket < $successRate) {
            return 'success';
        }

        if ($bucket < $successRate + $failureRate) {
            return 'failed';
        }

        return 'timeout';
    }

    /**
     * Build a deterministic provider transaction ID from the idempotency key.
     */
    private function buildProviderTxId(string $idempotencyKey): string
    {
        return 'mock_tx_'.abs(crc32($idempotencyKey));
    }

    /**
     * Success rate percentage (0–100). Defaults to 80.
     */
    private function successRate(): int
    {
        return max(0, min(100, (int) config('services.mock_gateway.success_rate', 80)));
    }

    /**
     * Failure rate percentage (0–100). Defaults to 10.
     * The timeout rate is the remainder: 100 - success - failure.
     */
    private function failureRate(): int
    {
        return max(0, min(100 - $this->successRate(), (int) config('services.mock_gateway.failure_rate', 10)));
    }
}
