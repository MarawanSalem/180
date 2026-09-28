<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the payouts table.
 *
 * Each row tracks a single attempted payment to one instructor within a batch.
 * The idempotency_key unique index is the physical guard against double-pay:
 * even if application-level checks fail, the DB rejects duplicate inserts.
 *
 * Status FSM:
 *   pending → processing → paid       (happy path)
 *   pending → failed                  (threshold not met OR permanent gateway error)
 *   processing → uncertain            (gateway timeout — funds may have moved)
 *   uncertain → paid | failed         (resolved by ReconcileUncertainPayoutsJob)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            // The batch this payout belongs to.
            $table->foreignId('payout_batch_id')
                ->constrained('payout_batches')
                ->restrictOnDelete();

            // The instructor receiving this payout.
            $table->foreignId('instructor_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Amount withheld and sent to the gateway, in cents.
            $table->unsignedBigInteger('amount_cents');

            // Current position in the payout FSM.
            $table->string('status')->default('pending');
            // Allowed: pending, processing, paid, failed, uncertain

            // Deterministic key constructed from batch_uuid + instructor_id.
            // Unique index prevents duplicate payout rows per batch per instructor.
            $table->string('idempotency_key')->unique('uq_payout_idempotency');

            // Transaction ID returned by the external payment gateway on success.
            $table->string('provider_tx_id')->nullable();

            // Human-readable failure message from the gateway on hard failure.
            $table->string('failure_reason')->nullable();

            // How many times this job has attempted to execute a gateway call.
            $table->unsignedInteger('attempts')->default(0);

            $table->timestamps();

            // === Indexes ===

            // Verify whether an instructor already has an active/pending payout.
            $table->index(['instructor_id', 'status'], 'idx_instructor_status');

            // Reconciliation engine: quickly finds uncertain payouts older than 5 minutes.
            $table->index(['status', 'updated_at'], 'idx_reconciliation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
