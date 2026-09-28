<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorLedgerEntry>
 */
class InstructorLedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state — an earning credit.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $instructorId = User::factory()->state(['is_instructor' => true])->create()->id;
        $periodId = SubscriptionPeriod::factory()->create(['status' => 'distributed'])->id;

        return [
            'instructor_id' => $instructorId,
            'payout_id' => null,
            'subscription_period_id' => $periodId,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => fake()->numberBetween(100, 50000),
            'idempotency_key' => "period_{$periodId}_instructor_{$instructorId}",
            'metadata' => null,
        ];
    }

    /**
     * An earning entry (positive credit from distributed period).
     */
    public function earning(int $instructorId, int $periodId, int $amountCents): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_id' => $instructorId,
            'subscription_period_id' => $periodId,
            'payout_id' => null,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => $amountCents,
            'idempotency_key' => "period_{$periodId}_instructor_{$instructorId}",
        ]);
    }

    /**
     * A payout_withhold entry (negative debit reserving funds).
     */
    public function withhold(int $instructorId, int $payoutId, int $amountCents): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_id' => $instructorId,
            'payout_id' => $payoutId,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::PayoutWithhold->value,
            'amount_cents' => -abs($amountCents),
            'idempotency_key' => "payout_{$payoutId}_withhold",
        ]);
    }

    /**
     * A payout_settled entry (negative debit finalising a paid payout).
     */
    public function settled(int $instructorId, int $payoutId, int $amountCents): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_id' => $instructorId,
            'payout_id' => $payoutId,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::PayoutSettled->value,
            'amount_cents' => -abs($amountCents),
            'idempotency_key' => "payout_{$payoutId}_settled",
        ]);
    }

    /**
     * An adjustment entry (positive credit restoring balance after failed payout).
     */
    public function adjustment(int $instructorId, int $payoutId, int $amountCents): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_id' => $instructorId,
            'payout_id' => $payoutId,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::Adjustment->value,
            'amount_cents' => abs($amountCents),
            'idempotency_key' => "payout_{$payoutId}_adjustment",
        ]);
    }

    /**
     * A clawback entry (negative debit reversing a refunded period's earnings).
     */
    public function clawback(int $instructorId, int $periodId, int $amountCents): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_id' => $instructorId,
            'payout_id' => null,
            'subscription_period_id' => $periodId,
            'type' => LedgerEntryType::Clawback->value,
            'amount_cents' => -abs($amountCents),
            'idempotency_key' => "period_{$periodId}_instructor_{$instructorId}_clawback",
        ]);
    }
}
