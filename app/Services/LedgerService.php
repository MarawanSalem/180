<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Ledger\FilterLedgerEntryDTO;
use App\Enums\LedgerEntryType;
use App\Exceptions\LedgerException;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Repositories\Interfaces\InstructorLedgerEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;

class LedgerService
{
    public function __construct(
        private readonly InstructorLedgerEntryRepositoryInterface $entries,
    ) {}

    /**
     * Return the current balance (SUM of amount_cents) for an instructor.
     * Applies a pessimistic FOR UPDATE lock when $lockForUpdate is true.
     */
    public function getBalance(int $instructorId, bool $lockForUpdate = false): int
    {
        return $this->entries->getBalanceCents($instructorId, $lockForUpdate);
    }

    /**
     * Return a paginated ledger history for an instructor.
     */
    public function listEntries(FilterLedgerEntryDTO $filter): LengthAwarePaginator
    {
        return $this->entries->getByInstructorPaginated(
            $filter->instructorId,
            $filter->perPage,
            $filter->type,
        );
    }

    /**
     * Insert a ledger entry, protecting against duplicate idempotency_key inserts.
     *
     * @param  array<string, mixed>  $attributes  Must include idempotency_key.
     *
     * @throws LedgerException when the idempotency_key already exists.
     */
    public function bookEntry(array $attributes): InstructorLedgerEntry
    {
        try {
            return $this->entries->create($attributes);
        } catch (UniqueConstraintViolationException) {
            throw LedgerException::duplicateIdempotencyKey($attributes['idempotency_key'] ?? '');
        }
    }

    /**
     * Book a payout_withhold (negative) ledger entry for the given payout.
     * Must be called inside a DB transaction with a pessimistic lock on the instructor.
     *
     * @throws LedgerException when the withhold entry already exists.
     */
    public function bookWithhold(Payout $payout): InstructorLedgerEntry
    {
        return $this->bookEntry([
            'instructor_id' => $payout->instructor_id,
            'payout_id' => $payout->id,
            'type' => LedgerEntryType::PayoutWithhold->value,
            'amount_cents' => -abs($payout->amount_cents),
            'idempotency_key' => "payout_{$payout->id}_withhold",
            'metadata' => null,
        ]);
    }

    /**
     * Convert an existing payout_withhold entry to payout_settled after a successful gateway call.
     * Amount is unchanged — this is an append-only semantic conversion, not a second debit.
     *
     * @throws LedgerException when the withhold entry is missing.
     */
    public function settleHold(Payout $payout): void
    {
        $entry = $this->entries->findByIdempotencyKey("payout_{$payout->id}_withhold");

        if ($entry === null) {
            throw LedgerException::withholdNotFound((int) $payout->id);
        }

        if ($entry->type === LedgerEntryType::PayoutSettled) {
            return;
        }

        $this->entries->convertType($entry, LedgerEntryType::PayoutSettled);
    }

    /**
     * Book an adjustment (positive) credit that restores the balance after a failed payout.
     *
     * @throws LedgerException when the adjustment entry already exists.
     */
    public function releaseHold(Payout $payout): void
    {
        $this->bookEntry([
            'instructor_id' => $payout->instructor_id,
            'payout_id' => $payout->id,
            'type' => LedgerEntryType::Adjustment->value,
            'amount_cents' => abs($payout->amount_cents),
            'idempotency_key' => "payout_{$payout->id}_adjustment",
            'metadata' => null,
        ]);
    }

    /**
     * Whether a withhold (or converted settled) entry already exists for this payout.
     */
    public function hasWithhold(Payout $payout): bool
    {
        return $this->entries->findByIdempotencyKey("payout_{$payout->id}_withhold") !== null;
    }
}
