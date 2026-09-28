<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the subscription_periods table.
 *
 * Each subscription is split into N discrete periods depending on plan tier:
 *   - Monthly      → 1 period  (30 days)
 *   - Three-Month  → 3 periods (~30 days each)
 *   - Annual       → 12 periods (~30 days each)
 *
 * Revenue is recognized at the END of each period when status transitions
 * from 'pending' to 'distributed'. Voided periods preserve audit history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->id();

            // Parent subscription this period belongs to.
            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->restrictOnDelete();

            // Sequential period number within the subscription (1-indexed).
            // Combined with subscription_id to enforce chronological uniqueness.
            $table->unsignedInteger('period_number');

            // Gross revenue amount allocated to this period, in cents.
            // For a $30.00 monthly plan: 3000 cents for period 1.
            // For a $300.00 annual plan: 2500 cents per period (12 × 2500 = 30000).
            $table->unsignedBigInteger('period_amount_cents');

            // Inclusive date boundaries of this recognition window.
            $table->date('period_start');
            $table->date('period_end');

            // Recognition lifecycle for this period.
            $table->string('status')->default('pending');
            // Allowed: pending, distributed, refunded, voided

            $table->timestamps();

            // === Indexes & Constraints ===

            // Unique index: prevents duplicate or out-of-order period rows for a subscription.
            $table->unique(['subscription_id', 'period_number'], 'uq_sub_period');

            // Accelerates queries that find all pending periods ready for distribution.
            $table->index(['status', 'period_end'], 'idx_period_status_end');

            // Accelerates period-level auditing from ledger entries.
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_periods');
    }
};
