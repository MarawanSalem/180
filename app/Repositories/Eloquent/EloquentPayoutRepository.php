<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Repositories\Interfaces\PayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentPayoutRepository implements PayoutRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new payout record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Payout
    {
        return Payout::create($attributes);
    }

    /**
     * Find an existing payout by idempotency key or create it with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function firstOrCreate(string $idempotencyKey, array $attributes): Payout
    {
        return Payout::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            $attributes,
        );
    }

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a payout by primary key with instructor and ledger entries, or null.
     */
    public function find(int $id): ?Payout
    {
        return Payout::with(['instructor', 'ledgerEntries'])->find($id);
    }

    /**
     * Find a payout by its idempotency key, or null.
     */
    public function findByIdempotencyKey(string $key): ?Payout
    {
        return Payout::where('idempotency_key', $key)->first();
    }

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update a payout record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Payout $payout, array $attributes): Payout
    {
        $payout->update($attributes);

        return $payout->fresh() ?? $payout;
    }

    /**
     * Transition a payout's status.
     */
    public function updateStatus(Payout $payout, PayoutStatus $status): Payout
    {
        $payout->update(['status' => $status->value]);

        return $payout->fresh() ?? $payout;
    }

    /**
     * Increment the attempts counter on a payout.
     */
    public function incrementAttempts(Payout $payout): void
    {
        $payout->increment('attempts');
    }

    // ==============================
    // Delete Operations
    // ==============================

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Return all uncertain payouts older than the given number of minutes.
     *
     * @return Collection<int, Payout>
     */
    public function getUncertainOlderThan(int $minutes = 5): Collection
    {
        return Payout::uncertainOlderThan($minutes)
            ->with(['instructor', 'batch'])
            ->get();
    }

    /**
     * Return a paginated list of all payouts.
     */
    public function getPaginated(
        int $perPage = 15,
        ?PayoutStatus $status = null,
        ?int $instructorId = null,
    ): LengthAwarePaginator {
        $query = Payout::query()
            ->with('instructor')
            ->latest();

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($instructorId !== null) {
            $query->where('instructor_id', $instructorId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Return a paginated list of payouts for a specific instructor.
     */
    public function getByInstructorPaginated(int $instructorId, int $perPage = 15): LengthAwarePaginator
    {
        return Payout::where('instructor_id', $instructorId)
            ->latest()
            ->paginate($perPage);
    }
}
