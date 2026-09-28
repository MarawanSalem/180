<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Enums\PayoutBatchStatus;
use App\Models\PayoutBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PayoutBatchRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new payout batch record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PayoutBatch;

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a payout batch by primary key, or null.
     */
    public function find(int $id): ?PayoutBatch;

    /**
     * Find a payout batch by its UUID, or null.
     */
    public function findByUuid(string $uuid): ?PayoutBatch;

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update a payout batch record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(PayoutBatch $batch, array $attributes): PayoutBatch;

    /**
     * Transition a batch's status.
     */
    public function updateStatus(PayoutBatch $batch, PayoutBatchStatus $status): PayoutBatch;

    // ==============================
    // Delete Operations
    // ==============================

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Return all batches currently in processing status.
     *
     * @return Collection<int, PayoutBatch>
     */
    public function getProcessing(): Collection;

    /**
     * Return a paginated list of all payout batches.
     */
    public function getPaginated(int $perPage = 15): LengthAwarePaginator;
}
