<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlanTier;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Interfaces\SubscriptionPeriodRepositoryInterface;
use App\Repositories\Interfaces\SubscriptionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionPeriodRepositoryInterface $periods,
    ) {}

    /**
     * List subscriptions owned by the given user.
     */
    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->subscriptions->getByUserPaginated((int) $user->id, $perPage);
    }

    /**
     * Reload a subscription with periods for API responses.
     */
    public function loadForShow(Subscription $subscription): Subscription
    {
        return $subscription->loadMissing('periods');
    }

    /**
     * Create a new subscription for a student and generate all amortization periods.
     *
     * @param  User  $actor  The student purchasing the subscription.
     * @param  PlanTier  $planTier  The selected plan tier.
     * @param  int  $priceCents  The gross price collected upfront (cents).
     * @param  int  $platformFeeBps  Platform commission in basis points (e.g. 3000 = 30%).
     * @param  Carbon  $startsAt  When the subscription begins.
     * @return Subscription Freshly loaded with periods relation.
     */
    public function createSubscription(
        User $actor,
        PlanTier $planTier,
        int $priceCents,
        int $platformFeeBps,
        Carbon $startsAt,
    ): Subscription {
        return DB::transaction(function () use ($actor, $planTier, $priceCents, $platformFeeBps, $startsAt): Subscription {
            $endsAt = $startsAt->copy()->addMonths($planTier->periodCount());

            $subscription = $this->subscriptions->create([
                'user_id' => $actor->id,
                'plan_tier' => $planTier->value,
                'price_cents' => $priceCents,
                'platform_fee_bps' => $platformFeeBps,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => SubscriptionStatus::Active->value,
            ]);

            $this->generatePeriods($subscription);

            return $this->subscriptions->find((int) $subscription->id) ?? $subscription;
        });
    }

    /**
     * Cancel a subscription.
     *
     * @throws SubscriptionException when the subscription is not active.
     */
    public function cancelSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            throw SubscriptionException::notUpgradeable((int) $subscription->id);
        }

        return $this->subscriptions->update($subscription, [
            'status' => SubscriptionStatus::Canceled->value,
        ]);
    }

    /**
     * Generate all amortization periods for a newly created subscription.
     * Each period receives an equal share of the gross price.
     */
    private function generatePeriods(Subscription $subscription): void
    {
        $planTier = PlanTier::from($subscription->plan_tier instanceof PlanTier
            ? $subscription->plan_tier->value
            : $subscription->plan_tier);
        $periodCount = $planTier->periodCount();
        $grossCents = $subscription->price_cents;

        // Use integer division; the first period absorbs any remainder.
        $baseAmount = (int) floor($grossCents / $periodCount);
        $remainder = $grossCents - ($baseAmount * $periodCount);

        $records = [];
        $start = Carbon::parse($subscription->starts_at);

        for ($i = 1; $i <= $periodCount; $i++) {
            $periodEnd = $start->copy()->addMonth()->subDay();

            $records[] = [
                'subscription_id' => $subscription->id,
                'period_number' => $i,
                'period_amount_cents' => $baseAmount + ($i === 1 ? $remainder : 0),
                'period_start' => $start->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => SubscriptionPeriodStatus::Pending->value,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            $start = $periodEnd->copy()->addDay();
        }

        $this->periods->insertMany($records);
    }
}
