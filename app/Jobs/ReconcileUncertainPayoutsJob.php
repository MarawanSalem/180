<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Repositories\Interfaces\PayoutRepositoryInterface;
use App\Services\LedgerService;
use App\Services\Payment\MockPaymentProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Resolves uncertain payouts by querying the gateway for their definitive status.
 *
 * Runs every 10 minutes via the Laravel scheduler (see routes/console.php).
 *
 * For each uncertain payout older than 5 minutes:
 *   - verifyStatus() returns the gateway's confirmed outcome.
 *   - Confirmed paid  → mark PAID, post payout_settled ledger entry.
 *   - Confirmed absent → mark FAILED, post adjustment credit.
 *
 * The 5-minute age threshold prevents racing the gateway response window.
 */
class ReconcileUncertainPayoutsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(
        PayoutRepositoryInterface $payouts,
        LedgerService $ledger,
        MockPaymentProvider $gateway,
    ): void {
        $uncertain = $payouts->getUncertainOlderThan(minutes: 5);

        foreach ($uncertain as $payout) {
            $this->reconcile($payout, $ledger, $gateway, $payouts);
        }
    }

    /**
     * Reconcile a single uncertain payout with the gateway's confirmed state.
     */
    private function reconcile(
        Payout $payout,
        LedgerService $ledger,
        MockPaymentProvider $gateway,
        PayoutRepositoryInterface $payouts,
    ): void {
        $result = $gateway->verifyStatus($payout->idempotency_key);

        DB::transaction(function () use ($payout, $result, $ledger, $payouts): void {
            if ($result->isSuccess()) {
                $ledger->settleHold($payout);

                $payouts->update($payout, [
                    'status' => PayoutStatus::Paid->value,
                    'provider_tx_id' => $result->providerTxId,
                ]);

                return;
            }

            // Gateway has no record — treat as a definitive failure.
            $ledger->releaseHold($payout);

            $payouts->update($payout, [
                'status' => PayoutStatus::Failed->value,
                'failure_reason' => $result->message ?? 'Reconciliation: gateway confirmed non-existence.',
            ]);
        });
    }
}
