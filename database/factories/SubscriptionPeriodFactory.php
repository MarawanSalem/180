<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionPeriodStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPeriod>
 */
class SubscriptionPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-2 months', '-1 month');
        $periodEnd = (clone $periodStart)->modify('+30 days');

        return [
            'subscription_id' => Subscription::factory(),
            'period_number' => 1,
            'period_amount_cents' => 3000,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'status' => SubscriptionPeriodStatus::Pending->value,
        ];
    }

    /**
     * Period that has already ended and is ready for distribution.
     */
    public function readyForDistribution(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->subDays(35)->toDateString(),
            'period_end' => now()->subDays(5)->toDateString(),
            'status' => SubscriptionPeriodStatus::Pending->value,
        ]);
    }

    /**
     * Period that has been distributed.
     */
    public function distributed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPeriodStatus::Distributed->value,
        ]);
    }

    /**
     * Period that is still in the future (not yet ended).
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->addDays(5)->toDateString(),
            'period_end' => now()->addDays(35)->toDateString(),
            'status' => SubscriptionPeriodStatus::Pending->value,
        ]);
    }

    /**
     * Period that has been voided (mid-term upgrade).
     */
    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPeriodStatus::Voided->value,
        ]);
    }
}
