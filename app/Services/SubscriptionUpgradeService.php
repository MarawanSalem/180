<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlanTier;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Repositories\Interfaces\SubscriptionPeriodRepositoryInterface;
use App\Repositories\Interfaces\SubscriptionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles mid-term plan upgrades with full proration accounting.
 *
 * Upgrade algorithm (from docs/SENIOR_BONUS.md):
 *   1. Calculate consumed days on the active period.
 *   2. Prorate the active period amount to consumed days only.
 *   3. Distribute the prorated period immediately (trigger RevenueAllocationService).
 *   4. Void all remaining pending periods on the old subscription.
 *   5. Mark old subscription as 'upgraded'.
 *   6. Compute the credit = (original active − prorated) + sum(voided pending amounts).
 *   7. Create a new subscription at full new-plan price (credit applied on the invoice).
 *   8. Generate all new amortization periods for the new plan.
 *   9. Assert: creditCents + netCashCents == newPriceCents.
 */
class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionPeriodRepositoryInterface $periods,
        private readonly SubscriptionService $subscriptionService,
        private readonly RevenueAllocationService $revenueAllocation,
    ) {}

    /**
     * Upgrade a student from their current active subscription to a new plan.
     *
     * @param  Subscription  $old  The active subscription to upgrade from.
     * @param  PlanTier  $newTier  The target plan tier.
     * @param  int  $newPriceCents  The gross price of the new plan (before credit).
     * @param  Collection<int, array{instructor_id: int, weight: float}>  $consumptionWeights
     *                                                                                         Instructor consumption weights for prorating the active period.
     * @param  Carbon  $upgradeDate  The date the upgrade takes effect (usually today).
     * @return Subscription The newly created subscription.
     *
     * @throws SubscriptionException when the old subscription is not upgradeable.
     */
    public function upgrade(
        Subscription $old,
        PlanTier $newTier,
        int $newPriceCents,
        Collection $consumptionWeights,
        Carbon $upgradeDate,
    ): Subscription {
        if ($old->status !== SubscriptionStatus::Active) {
            throw SubscriptionException::notUpgradeable((int) $old->id);
        }

        // Load the currently active period (the one in progress on upgrade date).
        $activePeriod = $this->findActivePeriod($old, $upgradeDate);

        if ($activePeriod === null) {
            throw SubscriptionException::notUpgradeable((int) $old->id);
        }

        return DB::transaction(function () use (
            $old,
            $newTier,
            $newPriceCents,
            $consumptionWeights,
            $upgradeDate,
            $activePeriod,
        ): Subscription {
            // Capture original amount before proration mutates the period.
            $originalAmountCents = (int) $activePeriod->period_amount_cents;

            // Step 1 & 2: Prorate the active period to consumed days only.
            $proratedCents = $this->calculateProratedAmount($activePeriod, $upgradeDate);
            $this->periods->update($activePeriod, ['period_amount_cents' => $proratedCents]);

            // Reload so RevenueAllocationService sees the updated amount.
            $activePeriod->refresh();

            // Step 3: Distribute prorated period immediately.
            if ($consumptionWeights->isNotEmpty()) {
                $this->revenueAllocation->allocatePeriod($activePeriod, $consumptionWeights);
            } else {
                // No instructor consumption — just mark as distributed.
                $this->periods->update($activePeriod, ['status' => SubscriptionPeriodStatus::Distributed->value]);
            }

            // Step 4: Void remaining pending periods; capture their amounts for credit.
            $voidedPendingCents = $this->voidRemainingPeriods($old, $activePeriod);

            // Step 5: Mark old subscription as upgraded.
            $this->subscriptions->update($old, ['status' => SubscriptionStatus::Upgraded->value]);

            // Step 6: Credit = unearned active remainder + voided future periods.
            $creditCents = $this->calculateCreditCents($originalAmountCents, $proratedCents, $voidedPendingCents);

            // Step 7 & 8: Create new subscription at full recognized value.
            $netCashCents = max(0, $newPriceCents - $creditCents);
            $actor = $old->user;

            $newSubscription = $this->subscriptionService->createSubscription(
                actor: $actor,
                planTier: $newTier,
                priceCents: $newPriceCents,
                platformFeeBps: $old->platform_fee_bps,
                startsAt: $upgradeDate,
            );

            // Step 9: Invariant — credit + net cash == new plan total value.
            $invariantTotal = $creditCents + $netCashCents;
            if ($invariantTotal !== $newPriceCents) {
                throw SubscriptionException::pennyInvariantViolated($newPriceCents, $invariantTotal);
            }

            return $newSubscription;
        });
    }

    /**
     * Find the period that is currently active (in-progress) on the upgrade date.
     */
    private function findActivePeriod(Subscription $subscription, Carbon $upgradeDate): ?SubscriptionPeriod
    {
        $upgradeDate = $upgradeDate->toDateString();

        return $subscription->periods()
            ->where('status', SubscriptionPeriodStatus::Pending->value)
            ->whereDate('period_start', '<=', $upgradeDate)
            ->whereDate('period_end', '>=', $upgradeDate)
            ->first();
    }

    /**
     * Calculate the prorated amount (cents) for the consumed portion of a period.
     *
     * Formula: total_amount × (consumed_days / total_period_days)
     * Uses floor() to stay within the period's gross budget.
     */
    private function calculateProratedAmount(SubscriptionPeriod $period, Carbon $upgradeDate): int
    {
        $periodStart = Carbon::parse($period->period_start);
        $periodEnd = Carbon::parse($period->period_end);
        $totalDays = $periodStart->diffInDays($periodEnd) + 1;
        $consumedDays = $periodStart->diffInDays($upgradeDate) + 1;

        return (int) floor($period->period_amount_cents * $consumedDays / $totalDays);
    }

    /**
     * Calculate unearned credit in cents transferred to the new subscription invoice.
     *
     * Credit = (original active period amount − prorated consumed) + sum of voided pending periods.
     */
    private function calculateCreditCents(int $originalAmountCents, int $proratedCents, int $voidedPendingCents): int
    {
        return ($originalAmountCents - $proratedCents) + $voidedPendingCents;
    }

    /**
     * Void all remaining pending periods for the old subscription, excluding the active one.
     *
     * @return int Sum of period_amount_cents for the voided periods (used as credit).
     */
    private function voidRemainingPeriods(Subscription $subscription, SubscriptionPeriod $activePeriod): int
    {
        $remaining = $subscription->periods()
            ->where('status', SubscriptionPeriodStatus::Pending->value)
            ->where('id', '!=', $activePeriod->id)
            ->get(['id', 'period_amount_cents']);

        $voidedPendingCents = (int) $remaining->sum('period_amount_cents');

        if ($remaining->isNotEmpty()) {
            $subscription->periods()
                ->whereIn('id', $remaining->pluck('id'))
                ->update(['status' => SubscriptionPeriodStatus::Voided->value]);
        }

        return $voidedPendingCents;
    }
}
