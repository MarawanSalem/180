<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionPeriodRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new subscription period.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SubscriptionPeriod;

    /**
     * Insert multiple subscription periods at once (bulk).
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function insertMany(array $records): void;

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a period by primary key, or null.
     */
    public function find(int $id): ?SubscriptionPeriod;

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update an existing period record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(SubscriptionPeriod $period, array $attributes): SubscriptionPeriod;

    // ==============================
    // Delete Operations
    // ==============================

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Return all periods that are ready for revenue distribution
     * (status=pending and period_end <= today).
     *
     * @return Collection<int, SubscriptionPeriod>
     */
    public function getReadyForDistribution(): Collection;

    /**
     * Return all periods for a given subscription, ordered by period_number.
     *
     * @return Collection<int, SubscriptionPeriod>
     */
    public function getBySubscription(int $subscriptionId): Collection;
}
