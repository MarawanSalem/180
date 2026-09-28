<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayoutBatchStatus;
use Carbon\Carbon;
use Database\Factories\PayoutBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents one execution cycle of the payout scheduler.
 *
 * A payout batch is created at the start of RunScheduledPayoutsCommand and
 * groups all individual instructor payout records for that run. Its status
 * reflects the aggregate outcome:
 *   processing       → jobs are still being processed
 *   completed        → all payouts settled (paid or legitimately failed)
 *   partially_failed → at least one payout ended in an unexpected failure state
 *
 * @property int $id
 * @property string $batch_uuid Globally unique UUID for this batch run
 * @property PayoutBatchStatus $status processing|completed|partially_failed
 * @property int $total_cents Aggregate amount of all PAID payouts in cents
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PayoutBatch extends Model
{
    /** @use HasFactory<PayoutBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_uuid',
        'status',
        'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutBatchStatus::class,
            'total_cents' => 'integer',
        ];
    }

    // ==============================
    // Relationships
    // ==============================

    /**
     * All individual instructor payout records in this batch.
     *
     * @return HasMany<Payout>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    // ==============================
    // Accessors & Mutators
    // ==============================

    // ==============================
    // Scopes
    // ==============================

    /** @param Builder<PayoutBatch> $query */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', PayoutBatchStatus::Processing->value);
    }

    /** @param Builder<PayoutBatch> $query */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', PayoutBatchStatus::Completed->value);
    }

    // ==============================
    // Events & Observers
    // ==============================

    // ==============================
    // Custom Methods
    // ==============================
}
