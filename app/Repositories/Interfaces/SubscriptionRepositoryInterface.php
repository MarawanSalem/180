<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new subscription.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Subscription;

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a subscription by primary key, or null.
     */
    public function find(int $id): ?Subscription;

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update an existing subscription record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Subscription $subscription, array $attributes): Subscription;

    // ==============================
    // Delete Operations
    // ==============================

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Return a paginated list of subscriptions for a given user.
     */
    public function getByUserPaginated(int $userId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Return all active subscriptions (for distribution processing).
     *
     * @return Collection<int, Subscription>
     */
    public function getActive(): Collection;
}
