<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the subscriptions table.
 *
 * Each row represents one subscription contract between a student and the platform.
 * Pricing is stored in integer cents (BIGINT) to avoid floating-point drift.
 * The platform fee is stored as basis points (bps), e.g. 3000 bps = 30%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // The student who purchased this subscription.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Plan tier determines the amortization window (1, 3, or 12 periods).
            $table->enum('plan_tier', ['monthly', 'three_month', 'annual']);

            // Total cash collected upfront, stored as cents (e.g. $30.00 → 3000).
            $table->unsignedBigInteger('price_cents');

            // Platform revenue share in basis points (100 bps = 1%). Default 30%.
            $table->unsignedBigInteger('platform_fee_bps')->default(3000);

            // Canonical subscription window boundaries.
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            // Lifecycle status of this subscription.
            $table->string('status')->default('active');
            // Allowed: active, canceled, refunded, upgraded

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
