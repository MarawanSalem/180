<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionPeriodStatus;
use Carbon\Carbon;
use Database\Factories\SubscriptionPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a single discrete revenue recognition window within a subscription.
 *
 * Status lifecycle:
 *   pending     → not yet distributed (awaiting period end date)
 *   distributed → earnings allocated to instructors; InstructorLedgerEntry rows created
 *   refunded    → subscription was refunded; clawback entries posted
 *   voided      → period cancelled as part of a mid-term upgrade; history preserved
 *
 * @property int $id
 * @property int $subscription_id
 * @property int $period_number 1-indexed sequence within the subscription
 * @property int $period_amount_cents Gross revenue for this window (cents)
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property SubscriptionPeriodStatus $status pending|distributed|refunded|voided
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SubscriptionPeriod extends Model
{
    /** @use HasFactory<SubscriptionPeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'period_number',
        'period_amount_cents',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionPeriodStatus::class,
            'period_number' => 'integer',
            'period_amount_cents' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    // ==============================
    // Relationships
    // ==============================

    /**
     * The subscription this period belongs to.
     *
     * @return BelongsTo<Subscription, SubscriptionPeriod>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The ledger entries generated when this period was distributed to instructors.
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

    /**
     * Periods that are pending distribution (not yet processed).
     *
     * @param  Builder<SubscriptionPeriod>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SubscriptionPeriodStatus::Pending->value);
    }

    /**
     * Periods whose revenue has already been allocated to instructors.
     *
     * @param  Builder<SubscriptionPeriod>  $query
     */
    public function scopeDistributed(Builder $query): Builder
    {
        return $query->where('status', SubscriptionPeriodStatus::Distributed->value);
    }

    /**
     * Periods that have been voided due to a mid-term plan upgrade.
     *
     * @param  Builder<SubscriptionPeriod>  $query
     */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', SubscriptionPeriodStatus::Voided->value);
    }

    /**
     * Periods whose end date has passed and are ready to be distributed.
     *
     * @param  Builder<SubscriptionPeriod>  $query
     */
    public function scopeReadyForDistribution(Builder $query): Builder
    {
        return $query->where('status', SubscriptionPeriodStatus::Pending->value)
            ->where('period_end', '<=', now()->toDateString());
    }

    // ==============================
    // Events & Observers
    // ==============================

    // ==============================
    // Custom Methods
    // ==============================
}
