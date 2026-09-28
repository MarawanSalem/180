<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the payout_batches table.
 *
 * A payout batch groups all individual instructor payouts triggered
 * by a single execution of RunScheduledPayoutsCommand. This allows
 * operations to audit a full run, track aggregate totals, and detect
 * partially-failed cycles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();

            // Globally unique identifier for this batch run.
            // Used as an external reference and for idempotent command re-triggers.
            $table->uuid('batch_uuid')->unique();

            // Overall status of this batch cycle.
            $table->string('status')->default('processing');
            // Allowed: processing, completed, partially_failed

            // Aggregate total of all payout amounts in this batch, in cents.
            // Updated as individual payouts transition to PAID.
            $table->unsignedBigInteger('total_cents')->default(0);

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
