<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Enums\LedgerEntryType;
use App\Models\InstructorLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InstructorLedgerEntryRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Insert a new ledger entry.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): InstructorLedgerEntry;

    /**
     * Bulk-insert multiple ledger entries at once.
     * All records must have unique idempotency_key values.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function insertMany(array $records): void;

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a ledger entry by primary key, or null.
     */
    public function find(int $id): ?InstructorLedgerEntry;

    /**
     * Find a ledger entry by its idempotency key, or null.
     */
    public function findByIdempotencyKey(string $key): ?InstructorLedgerEntry;

    // ==============================
    // Update Operations
    // ==============================

    // Append-only except convertType: sole allowed mutation is
    // payout_withhold → payout_settled (amount_cents unchanged).

    /**
     * Convert a ledger entry's type in place (hold → settled only).
     * Does not change amount_cents or any other column.
     */
    public function convertType(InstructorLedgerEntry $entry, LedgerEntryType $type): InstructorLedgerEntry;

    // ==============================
    // Delete Operations
    // ==============================

    // (This table is append-only — no delete operations.)

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Compute the current balance (sum of amount_cents) for a given instructor.
     * Uses a pessimistic lock on the instructor row when $lockForUpdate is true.
     */
    public function getBalanceCents(int $instructorId, bool $lockForUpdate = false): int;

    /**
     * Return all ledger entries of a given type for an instructor.
     *
     * @return Collection<int, InstructorLedgerEntry>
     */
    public function getByInstructorAndType(int $instructorId, LedgerEntryType $type): Collection;

    /**
     * Return a paginated list of entries for a given instructor.
     */
    public function getByInstructorPaginated(
        int $instructorId,
        int $perPage = 15,
        ?LedgerEntryType $type = null,
    ): LengthAwarePaginator;
}
