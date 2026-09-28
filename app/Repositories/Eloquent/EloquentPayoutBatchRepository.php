<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PayoutBatchStatus;
use App\Models\PayoutBatch;
use App\Repositories\Interfaces\PayoutBatchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentPayoutBatchRepository implements PayoutBatchRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new payout batch record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PayoutBatch
    {
        return PayoutBatch::create($attributes);
    }

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a payout batch by primary key with its payouts, or null.
     */
    public function find(int $id): ?PayoutBatch
    {
        return PayoutBatch::with('payouts')->find($id);
    }

    /**
     * Find a payout batch by its UUID, or null.
     */
    public function findByUuid(string $uuid): ?PayoutBatch
    {
        return PayoutBatch::where('batch_uuid', $uuid)->first();
    }

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update a payout batch record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(PayoutBatch $batch, array $attributes): PayoutBatch
    {
        $batch->update($attributes);

        return $batch->fresh() ?? $batch;
    }

    /**
     * Transition a batch's status.
     */
    public function updateStatus(PayoutBatch $batch, PayoutBatchStatus $status): PayoutBatch
    {
        $batch->update(['status' => $status->value]);

        return $batch->fresh() ?? $batch;
    }

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
    public function getProcessing(): Collection
    {
        return PayoutBatch::processing()->with('payouts')->get();
    }

    /**
     * Return a paginated list of all payout batches.
     */
    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return PayoutBatch::latest()->paginate($perPage);
    }
}
