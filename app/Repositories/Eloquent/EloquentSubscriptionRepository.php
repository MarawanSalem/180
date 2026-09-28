<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Subscription;
use App\Repositories\Interfaces\SubscriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new subscription.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Subscription
    {
        return Subscription::create($attributes);
    }

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a subscription by primary key with its periods, or null.
     */
    public function find(int $id): ?Subscription
    {
        return Subscription::with('periods')->find($id);
    }

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update an existing subscription record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Subscription $subscription, array $attributes): Subscription
    {
        $subscription->update($attributes);

        return $subscription->fresh(['periods']) ?? $subscription;
    }

    // ==============================
    // Delete Operations
    // ==============================

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Return a paginated list of subscriptions for a given user.
     */
    public function getByUserPaginated(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Subscription::with('periods')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Return all active subscriptions (for distribution processing).
     *
     * @return Collection<int, Subscription>
     */
    public function getActive(): Collection
    {
        return Subscription::active()->with('periods')->get();
    }
}
