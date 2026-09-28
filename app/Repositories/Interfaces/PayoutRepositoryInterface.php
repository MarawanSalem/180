<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PayoutRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new payout record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Payout;

    /**
     * Find an existing payout by idempotency key or create it with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function firstOrCreate(string $idempotencyKey, array $attributes): Payout;

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a payout by primary key, or null.
     */
    public function find(int $id): ?Payout;

    /**
     * Find a payout by its idempotency key, or null.
     */
    public function findByIdempotencyKey(string $key): ?Payout;

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update a payout record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Payout $payout, array $attributes): Payout;

    /**
     * Transition a payout's status.
     */
    public function updateStatus(Payout $payout, PayoutStatus $status): Payout;

    /**
     * Increment the attempts counter on a payout.
     */
    public function incrementAttempts(Payout $payout): void;

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
    public function getUncertainOlderThan(int $minutes = 5): Collection;

    /**
     * Return a paginated list of payouts, optionally filtered.
     */
    public function getPaginated(
        int $perPage = 15,
        ?PayoutStatus $status = null,
        ?int $instructorId = null,
    ): LengthAwarePaginator;

    /**
     * Return a paginated list of payouts for a specific instructor.
     */
    public function getByInstructorPaginated(int $instructorId, int $perPage = 15): LengthAwarePaginator;
}
