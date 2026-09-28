<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SubscriptionPeriod;
use App\Repositories\Interfaces\SubscriptionPeriodRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentSubscriptionPeriodRepository implements SubscriptionPeriodRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new subscription period.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SubscriptionPeriod
    {
        return SubscriptionPeriod::create($attributes);
    }

    /**
     * Bulk-insert multiple subscription periods at once.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function insertMany(array $records): void
    {
        SubscriptionPeriod::insert($records);
    }

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a period by primary key with its ledger entries, or null.
     */
    public function find(int $id): ?SubscriptionPeriod
    {
        return SubscriptionPeriod::with('ledgerEntries')->find($id);
    }

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update an existing period record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(SubscriptionPeriod $period, array $attributes): SubscriptionPeriod
    {
        $period->update($attributes);

        return $period->fresh() ?? $period;
    }

    // ==============================
    // Delete Operations
    // ==============================

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Return all periods ready for revenue distribution (pending and period_end <= today).
     *
     * @return Collection<int, SubscriptionPeriod>
     */
    public function getReadyForDistribution(): Collection
    {
        return SubscriptionPeriod::readyForDistribution()
            ->with('subscription')
            ->get();
    }

    /**
     * Return all periods for a given subscription, ordered by period_number.
     *
     * @return Collection<int, SubscriptionPeriod>
     */
    public function getBySubscription(int $subscriptionId): Collection
    {
        return SubscriptionPeriod::where('subscription_id', $subscriptionId)
            ->orderBy('period_number')
            ->get();
    }
}
