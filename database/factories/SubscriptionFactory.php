<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlanTier;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tier = fake()->randomElement(PlanTier::cases());
        $startsAt = fake()->dateTimeBetween('-6 months', 'now');
        $endsAt = (clone $startsAt)->modify("+{$tier->periodCount()} months");

        return [
            'user_id' => User::factory(),
            'plan_tier' => $tier->value,
            'price_cents' => fake()->randomElement([3000, 9000, 30000]),
            'platform_fee_bps' => 3000,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => SubscriptionStatus::Active->value,
        ];
    }

    /**
     * Subscription with the monthly plan tier.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_tier' => PlanTier::Monthly->value,
            'price_cents' => 3000,
        ]);
    }

    /**
     * Subscription with the three-month plan tier.
     */
    public function threeMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_tier' => PlanTier::ThreeMonth->value,
            'price_cents' => 9000,
        ]);
    }

    /**
     * Subscription with the annual plan tier.
     */
    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_tier' => PlanTier::Annual->value,
            'price_cents' => 30000,
        ]);
    }

    /**
     * Mark the subscription as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    /**
     * Mark the subscription as upgraded.
     */
    public function upgraded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Upgraded->value,
        ]);
    }

    /**
     * Mark the subscription as canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Canceled->value,
        ]);
    }
}
