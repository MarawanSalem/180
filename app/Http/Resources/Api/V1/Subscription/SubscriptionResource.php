<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Subscription;

use App\Enums\PlanTier;
use App\Enums\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $user_id
 * @property PlanTier $plan_tier
 * @property int $price_cents
 * @property int $platform_fee_bps
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property SubscriptionStatus $status
 * @property Carbon $created_at
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_tier' => $this->plan_tier?->value,
            'price_cents' => $this->price_cents,
            'platform_fee_bps' => $this->platform_fee_bps,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'periods' => SubscriptionPeriodResource::collection($this->whenLoaded('periods')),
        ];
    }
}
