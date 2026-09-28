<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayoutStatus;
use Carbon\Carbon;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a single attempted payout to one instructor within a batch.
 *
 * Status FSM (see docs/SYSTEM_ARCHITECTURE_AND_ERD.md §4):
 *   pending     → initial state when the payout record is created
 *   processing  → hold has been booked; gateway call is in progress
 *   paid        → gateway confirmed success; hold converted to payout_settled
 *   failed      → hard failure (threshold not met, or permanent 4xx/5xx)
 *   uncertain   → timeout or dropped connection; funds may have moved
 *
 * The idempotency_key unique index (uq_payout_idempotency) is the physical
 * storage-level guard that prevents a second payout row being created for the
 * same instructor in the same batch, regardless of concurrent workers.
 *
 * @property int $id
 * @property int $payout_batch_id
 * @property int $instructor_id
 * @property int $amount_cents Amount withheld and sent to gateway (cents)
 * @property PayoutStatus $status pending|processing|paid|failed|uncertain
 * @property string $idempotency_key Deterministic: "{batch_uuid}_{instructor_id}"
 * @property string|null $provider_tx_id Gateway transaction reference on success
 * @property string|null $failure_reason Human-readable reason on hard failure
 * @property int $attempts Number of gateway call attempts made
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'payout_batch_id',
        'instructor_id',
        'amount_cents',
        'status',
        'idempotency_key',
        'provider_tx_id',
        'failure_reason',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_cents' => 'integer',
            'attempts' => 'integer',
        ];
    }

    // ==============================
    // Relationships
    // ==============================

    /**
     * The batch this payout belongs to.
     *
     * @return BelongsTo<PayoutBatch, Payout>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    /**
     * The instructor receiving this payout.
     *
     * @return BelongsTo<User, Payout>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * All ledger entries linked to this payout (withhold, settled, adjustment).
     *
     * @return HasMany<InstructorLedgerEntry>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InstructorLedgerEntry::class);
    }

    // ==============================
    // Accessors & Mutators
    // ==============================

    // ==============================
    // Scopes
    // ==============================

    /** @param Builder<Payout> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Pending->value);
    }

    /** @param Builder<Payout> $query */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Processing->value);
    }

    /** @param Builder<Payout> $query */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Paid->value);
    }

    /** @param Builder<Payout> $query */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Failed->value);
    }

    /**
     * Uncertain payouts older than the given minutes threshold.
     * Used by ReconcileUncertainPayoutsJob to find stale unresolved records.
     *
     * @param  Builder<Payout>  $query
     */
    public function scopeUncertainOlderThan(Builder $query, int $minutes = 5): Builder
    {
        return $query->where('status', PayoutStatus::Uncertain->value)
            ->where('updated_at', '<=', now()->subMinutes($minutes));
    }

    // ==============================
    // Events & Observers
    // ==============================

    // ==============================
    // Custom Methods
    // ==============================

    /**
     * Check whether this payout is in a terminal (non-retryable) state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
