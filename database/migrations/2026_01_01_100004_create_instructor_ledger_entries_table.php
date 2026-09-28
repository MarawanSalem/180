<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the instructor_ledger_entries table.
 *
 * This is the financial core of the system. It is an append-only immutable ledger.
 * No row is ever updated or deleted after creation. An instructor's current balance
 * is always computed as: SELECT SUM(amount_cents) WHERE instructor_id = ?
 *
 * Entry Types:
 *   earning         — positive credit: instructor share of a distributed period
 *   payout_withhold — negative debit:  funds reserved before gateway call (Phase 1)
 *   payout_settled  — negative debit:  final settlement record after gateway confirms
 *   clawback        — negative debit:  revenue reversal (e.g. subscription refund)
 *   adjustment      — positive credit: restores balance after a failed/uncertain payout
 *
 * Sign Convention:
 *   Credits (earnings, adjustments) → amount_cents > 0
 *   Debits  (withholds, settlements, clawbacks) → amount_cents < 0
 *
 * The idempotency_key unique index is the storage-level guarantee that a period
 * allocation or payout hold cannot be inserted more than once, even under concurrent
 * worker failures and retries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_ledger_entries', function (Blueprint $table) {
            $table->id();

            // The instructor whose balance this entry affects.
            $table->foreignId('instructor_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Linked payout record (populated for withhold/settled/adjustment entries).
            $table->foreignId('payout_id')
                ->nullable()
                ->constrained('payouts')
                ->nullOnDelete();

            // Linked subscription period (populated for earning entries).
            $table->foreignId('subscription_period_id')
                ->nullable()
                ->constrained('subscription_periods')
                ->nullOnDelete();

            // Type of ledger event.
            $table->enum('type', [
                'earning',
                'payout_withhold',
                'payout_settled',
                'clawback',
                'adjustment',
            ]);

            // Signed integer amount in cents.
            // Credits are positive; debits are negative.
            // Must be BIGINT to support tens of millions of rows without overflow.
            $table->bigInteger('amount_cents');

            // Deterministic idempotency key prevents duplicate entries.
            // Format examples:
            //   earning:     "period_{period_id}_instructor_{instructor_id}"
            //   withhold:    "payout_{payout_id}_withhold"
            //   settled:     "payout_{payout_id}_settled"
            //   adjustment:  "payout_{payout_id}_adjustment"
            $table->string('idempotency_key')->unique('uq_ledger_idempotency');

            // Arbitrary structured context (e.g. consumption_weight, platform_fee_cents).
            $table->json('metadata')->nullable();

            // No updated_at: this table is append-only and rows never change.
            $table->timestamp('created_at')->useCurrent();

            // === Indexes ===

            // Primary index for balance queries: SUM(amount_cents) per instructor.
            // Including `type` allows selective sums (e.g. earnings only).
            $table->index(['instructor_id', 'type'], 'idx_instructor_type');

            // Enables fast period-level audit: all entries for a given period.
            $table->index('subscription_period_id', 'idx_period_id');

            // Enables payout-level audit: all entries linked to a payout.
            $table->index('payout_id', 'idx_payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_ledger_entries');
    }
};
