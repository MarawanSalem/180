<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionPeriodStatus;
use App\Exceptions\SubscriptionException;
use App\Models\SubscriptionPeriod;
use App\Repositories\Interfaces\InstructorLedgerEntryRepositoryInterface;
use App\Repositories\Interfaces\SubscriptionPeriodRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAllocationService
{
    public function __construct(
        private readonly SubscriptionPeriodRepositoryInterface $periods,
        private readonly InstructorLedgerEntryRepositoryInterface $entries,
    ) {}

    /**
     * Allocate a subscription period's revenue among instructors using the
     * Hamilton-Hare (Largest Remainder) method to ensure zero penny leakage.
     *
     * Enforces: sum(instructor_shares) + platform_cut == period_gross_cents
     *
     * @param  SubscriptionPeriod  $period  The period to distribute.
     * @param  Collection<int, array{instructor_id: int, weight: float}>  $consumptionWeights
     *                                                                                         Each item must have an 'instructor_id' (int) and a 'weight' (float, must sum to 1.0).
     *
     * @throws SubscriptionException when the period is already distributed or invariant violated.
     */
    public function allocatePeriod(SubscriptionPeriod $period, Collection $consumptionWeights): void
    {
        if ($period->status === SubscriptionPeriodStatus::Distributed) {
            throw SubscriptionException::periodAlreadyDistributed($period->id);
        }

        $grossCents = $period->period_amount_cents;
        $platformFeeBps = $period->subscription->platform_fee_bps;
        $platformCutCents = (int) floor($grossCents * $platformFeeBps / 10000);
        $poolCents = $grossCents - $platformCutCents;

        $shares = $this->hamiltonHare($consumptionWeights, $poolCents);

        // Enforce penny-perfect invariant before writing anything.
        $allocatedTotal = array_sum(array_column($shares, 'amount_cents'));
        if ($allocatedTotal + $platformCutCents !== $grossCents) {
            throw SubscriptionException::pennyInvariantViolated($grossCents, $allocatedTotal + $platformCutCents);
        }

        DB::transaction(function () use ($period, $shares): void {
            $now = now()->toDateTimeString();
            $records = [];

            foreach ($shares as $share) {
                $records[] = [
                    'instructor_id' => $share['instructor_id'],
                    'payout_id' => null,
                    'subscription_period_id' => $period->id,
                    'type' => LedgerEntryType::Earning->value,
                    'amount_cents' => $share['amount_cents'],
                    'idempotency_key' => "period_{$period->id}_instructor_{$share['instructor_id']}",
                    'metadata' => json_encode(['consumption_weight' => $share['weight']]),
                    'created_at' => $now,
                ];
            }

            $this->entries->insertMany($records);

            $this->periods->update($period, ['status' => SubscriptionPeriodStatus::Distributed->value]);
        });
    }

    /**
     * Distribute $poolCents among instructors using the Hamilton-Hare method.
     *
     * @param  Collection<int, array{instructor_id: int, weight: float}>  $consumptionWeights
     * @return array<int, array{instructor_id: int, amount_cents: int, weight: float}>
     */
    private function hamiltonHare(Collection $consumptionWeights, int $poolCents): array
    {
        // Compute exact shares and floor values.
        $shares = $consumptionWeights->map(function (array $row) use ($poolCents): array {
            $exact = $poolCents * $row['weight'];

            return [
                'instructor_id' => $row['instructor_id'],
                'weight' => $row['weight'],
                'exact' => $exact,
                'base' => (int) floor($exact),
                'remainder' => $exact - floor($exact),
            ];
        })->values()->all();

        $baseTotal = array_sum(array_column($shares, 'base'));
        $remainingPennies = $poolCents - $baseTotal;

        // Sort by remainder descending to assign leftover pennies.
        usort($shares, fn (array $a, array $b): int => $b['remainder'] <=> $a['remainder']);

        foreach ($shares as $i => &$share) {
            $share['amount_cents'] = $share['base'] + ($i < $remainingPennies ? 1 : 0);
        }
        unset($share);

        return $shares;
    }
}
