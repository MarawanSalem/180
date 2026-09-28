<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayoutBatchStatus;
use App\Models\PayoutBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PayoutBatch>
 */
class PayoutBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_uuid' => (string) Str::uuid(),
            'status' => PayoutBatchStatus::Processing->value,
            'total_cents' => 0,
        ];
    }

    /**
     * Batch that has completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutBatchStatus::Completed->value,
            'total_cents' => fake()->numberBetween(200000, 5000000),
        ]);
    }

    /**
     * Batch that partially failed.
     */
    public function partiallyFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutBatchStatus::PartiallyFailed->value,
        ]);
    }
}
