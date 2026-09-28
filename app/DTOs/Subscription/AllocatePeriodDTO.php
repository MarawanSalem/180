<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use Illuminate\Support\Collection;

readonly class AllocatePeriodDTO
{
    /**
     * @param  Collection<int, array{instructor_id: int, weight: float}>  $consumptionWeights
     */
    public function __construct(
        public Collection $consumptionWeights,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        /** @var array<int, array{instructor_id: int, weight: float}> $rawWeights */
        $rawWeights = $data['consumption_weights'] ?? [];

        $weights = collect($rawWeights)->map(fn (array $w): array => [
            'instructor_id' => (int) $w['instructor_id'],
            'weight' => (float) $w['weight'],
        ]);

        return new self(consumptionWeights: $weights);
    }
}
