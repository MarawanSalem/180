<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\LedgerEntryType;
use App\Models\InstructorLedgerEntry;
use App\Repositories\Interfaces\InstructorLedgerEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentInstructorLedgerEntryRepository implements InstructorLedgerEntryRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Insert a new ledger entry.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): InstructorLedgerEntry
    {
        return InstructorLedgerEntry::create($attributes);
    }

    /**
     * Bulk-insert multiple ledger entries at once.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function insertMany(array $records): void
    {
        InstructorLedgerEntry::insert($records);
    }

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a ledger entry by primary key, or null.
     */
    public function find(int $id): ?InstructorLedgerEntry
    {
        return InstructorLedgerEntry::find($id);
    }

    /**
     * Find a ledger entry by its idempotency key, or null.
     */
    public function findByIdempotencyKey(string $key): ?InstructorLedgerEntry
    {
        return InstructorLedgerEntry::where('idempotency_key', $key)->first();
    }

    // ==============================
    // Update Operations
    // ==============================

    // Append-only except convertType: sole allowed mutation is
    // payout_withhold → payout_settled (amount_cents unchanged).

    /**
     * Convert a ledger entry's type in place (hold → settled only).
     * Does not change amount_cents or any other column.
     */
    public function convertType(InstructorLedgerEntry $entry, LedgerEntryType $type): InstructorLedgerEntry
    {
        $entry->update(['type' => $type->value]);

        return $entry->fresh() ?? $entry;
    }

    // ==============================
    // Delete Operations
    // ==============================

    // (This table is append-only — no delete operations.)

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Compute the current balance (SUM of amount_cents) for a given instructor.
     * Applies a FOR UPDATE lock when $lockForUpdate is true.
     */
    public function getBalanceCents(int $instructorId, bool $lockForUpdate = false): int
    {
        $query = InstructorLedgerEntry::where('instructor_id', $instructorId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return (int) $query->sum('amount_cents');
    }

    /**
     * Return all ledger entries of a given type for an instructor.
     *
     * @return Collection<int, InstructorLedgerEntry>
     */
    public function getByInstructorAndType(int $instructorId, LedgerEntryType $type): Collection
    {
        return InstructorLedgerEntry::where('instructor_id', $instructorId)
            ->where('type', $type->value)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Return a paginated list of entries for a given instructor.
     */
    public function getByInstructorPaginated(
        int $instructorId,
        int $perPage = 15,
        ?LedgerEntryType $type = null,
    ): LengthAwarePaginator {
        $query = InstructorLedgerEntry::query()
            ->where('instructor_id', $instructorId)
            ->orderByDesc('created_at');

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query->paginate($perPage);
    }
}
