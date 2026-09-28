<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use App\Enums\PlanTier;
use Carbon\Carbon;

readonly class CreateSubscriptionDTO
{
    public function __construct(
        public PlanTier $planTier,
        public int $priceCents,
        public int $platformFeeBps,
        public Carbon $startsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            planTier: PlanTier::from((string) $data['plan_tier']),
            priceCents: (int) $data['price_cents'],
            platformFeeBps: (int) $data['platform_fee_bps'],
            startsAt: Carbon::parse($data['starts_at']),
        );
    }
}
