<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerEntryType;
use Carbon\Carbon;
use Database\Factories\InstructorLedgerEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The financial core of the system — an append-only immutable ledger entry.
 *
 * IMMUTABILITY RULE: Rows are never deleted. Amounts are never changed.
 * The sole allowed mutation is converting payout_withhold → payout_settled
 * (same amount_cents and idempotency_key). Other corrections post offsetting entries.
 *
 * Balance Calculation (always dynamic, never stored):
 *   SELECT SUM(amount_cents) FROM instructor_ledger_entries WHERE instructor_id = ?
 *
 * Sign Convention:
 *   Credits (earnings, adjustments) → amount_cents is POSITIVE
 *   Debits  (withholds, settlements, clawbacks) → amount_cents is NEGATIVE
 *
 * Entry Types:
 *   earning         — instructor's share from a distributed subscription_period
 *   payout_withhold — reserve hold before gateway call (Phase 1 of 2-phase payout)
 *   payout_settled  — successful payout (converted in place from withhold)
 *   clawback        — revenue reversal for refunded subscriptions
 *   adjustment      — restores balance after a failed or reverted payout_withhold
 *
 * Idempotency Key Patterns:
 *   earning:                 "period_{period_id}_instructor_{instructor_id}"
 *   payout_withhold/settled: "payout_{payout_id}_withhold" (type changes on settle)
 *   adjustment:              "payout_{payout_id}_adjustment"
 *   clawback:                "period_{period_id}_instructor_{instructor_id}_clawback"
 *
 * @property int $id
 * @property int $instructor_id
 * @property int|null $payout_id Linked payout (withhold/settled/adjustment entries)
 * @property int|null $subscription_period_id Linked period (earning/clawback entries)
 * @property LedgerEntryType $type earning|payout_withhold|payout_settled|clawback|adjustment
 * @property int $amount_cents Signed: positive = credit, negative = debit
 * @property string $idempotency_key Unique key preventing duplicate inserts
 * @property array|null $metadata Structured context (consumption_weight, platform_fee, etc.)
 * @property Carbon $created_at
 */
class InstructorLedgerEntry extends Model
{
    /** @use HasFactory<InstructorLedgerEntryFactory> */
    use HasFactory;

    /**
     * This table is append-only — no rows are ever updated.
     */
    public $timestamps = false;

    /**
     * We manage created_at manually via the DB default (useCurrent()).
     */
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'instructor_id',
        'payout_id',
        'subscription_period_id',
        'type',
        'amount_cents',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount_cents' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ==============================
    // Relationships
    // ==============================

    /**
     * The instructor this ledger entry belongs to.
     *
     * @return BelongsTo<User, InstructorLedgerEntry>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * The payout record linked to this entry (for withhold/settled/adjustment types).
     *
     * @return BelongsTo<Payout, InstructorLedgerEntry>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    /**
     * The subscription period that triggered this entry (for earning/clawback types).
     *
     * @return BelongsTo<SubscriptionPeriod, InstructorLedgerEntry>
     */
    public function subscriptionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPeriod::class);
    }

    // ==============================
    // Accessors & Mutators
    // ==============================

    // ==============================
    // Scopes
    // ==============================

    /** @param Builder<InstructorLedgerEntry> $query */
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', LedgerEntryType::Earning->value);
    }

    /** @param Builder<InstructorLedgerEntry> $query */
    public function scopeWithholds(Builder $query): Builder
    {
        return $query->where('type', LedgerEntryType::PayoutWithhold->value);
    }

    /** @param Builder<InstructorLedgerEntry> $query */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('amount_cents', '>', 0);
    }

    /** @param Builder<InstructorLedgerEntry> $query */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('amount_cents', '<', 0);
    }

    // ==============================
    // Events & Observers
    // ==============================

    // ==============================
    // Custom Methods
    // ==============================

    /**
     * Whether this entry represents a credit (money flowing in to the instructor).
     */
    public function isCredit(): bool
    {
        return $this->amount_cents > 0;
    }

    /**
     * Whether this entry represents a debit (money flowing out of the instructor's balance).
     */
    public function isDebit(): bool
    {
        return $this->amount_cents < 0;
    }
}
