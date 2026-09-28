<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanTier;
use App\Enums\SubscriptionStatus;
use Carbon\Carbon;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a single subscription contract between a student and the platform.
 *
 * @property int $id
 * @property int $user_id
 * @property PlanTier $plan_tier monthly|three_month|annual
 * @property int $price_cents Total upfront cash collected (cents)
 * @property int $platform_fee_bps Platform share in basis points (3000 = 30%)
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property SubscriptionStatus $status active|canceled|refunded|upgraded
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_tier',
        'price_cents',
        'platform_fee_bps',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'plan_tier' => PlanTier::class,
            'status' => SubscriptionStatus::class,
            'price_cents' => 'integer',
            'platform_fee_bps' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    // ==============================
    // Relationships
    // ==============================

    /**
     * The student who purchased this subscription.
     *
     * @return BelongsTo<User, Subscription>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The discrete recognition periods this subscription is amortized into.
     * Monthly → 1 period, Three-month → 3 periods, Annual → 12 periods.
     *
     * @return HasMany<SubscriptionPeriod>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class)->orderBy('period_number');
    }

    // ==============================
    // Accessors & Mutators
    // ==============================

    // ==============================
    // Scopes
    // ==============================

    /** @param Builder<Subscription> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active->value);
    }

    /** @param Builder<Subscription> $query */
    public function scopeUpgraded(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Upgraded->value);
    }

    // ==============================
    // Events & Observers
    // ==============================

    // ==============================
    // Custom Methods
    // ==============================
}
