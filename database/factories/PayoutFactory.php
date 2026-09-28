<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $batchUuid = (string) Str::uuid();

        return [
            'payout_batch_id' => PayoutBatch::factory(),
            'instructor_id' => User::factory()->state(['is_instructor' => true]),
            'amount_cents' => fake()->numberBetween(2000, 500000),
            'status' => PayoutStatus::Pending->value,
            'idempotency_key' => $batchUuid.'_'.fake()->unique()->randomNumber(5),
            'provider_tx_id' => null,
            'failure_reason' => null,
            'attempts' => 0,
        ];
    }

    /**
     * Payout in pending state.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::Pending->value,
        ]);
    }

    /**
     * Payout in processing state.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::Processing->value,
            'attempts' => 1,
        ]);
    }

    /**
     * Successfully paid payout.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::Paid->value,
            'provider_tx_id' => 'mock_tx_'.fake()->numerify('########'),
            'attempts' => 1,
        ]);
    }

    /**
     * Hard-failed payout.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::Failed->value,
            'failure_reason' => 'Gateway rejected the payout request.',
            'attempts' => 1,
        ]);
    }

    /**
     * Uncertain payout (gateway timed out).
     */
    public function uncertain(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::Uncertain->value,
            'attempts' => 1,
        ]);
    }

    /**
     * Uncertain payout that is older than the reconciliation threshold.
     */
    public function uncertainStale(int $minutesOld = 10): static
    {
        return $this->uncertain()->afterCreating(function (Payout $payout) use ($minutesOld): void {
            $payout->forceFill(['updated_at' => now()->subMinutes($minutesOld)])->saveQuietly();
        });
    }
}
