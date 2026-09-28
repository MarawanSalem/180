<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use App\Enums\PlanTier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class UpgradeSubscriptionDTO
{
    /**
     * @param  Collection<int, array{instructor_id: int, weight: float}>  $consumptionWeights
     */
    public function __construct(
        public PlanTier $newTier,
        public int $newPriceCents,
        public Collection $consumptionWeights,
        public Carbon $upgradeDate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        /** @var array<int, array{instructor_id: int, weight: float}> $weights */
        $rawWeights = $data['consumption_weights'] ?? [];

        $weights = collect($rawWeights)->map(fn (array $w): array => [
            'instructor_id' => (int) $w['instructor_id'],
            'weight' => (float) $w['weight'],
        ]);

        return new self(
            newTier: PlanTier::from((string) $data['new_tier']),
            newPriceCents: (int) $data['new_price_cents'],
            consumptionWeights: $weights,
            upgradeDate: Carbon::parse($data['upgrade_date'] ?? now()),
        );
    }
}
