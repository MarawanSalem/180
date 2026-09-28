<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Subscription;

use App\Enums\SubscriptionPeriodStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int $period_number
 * @property int $period_amount_cents
 * @property string $period_start
 * @property string $period_end
 * @property SubscriptionPeriodStatus $status
 * @property Carbon $created_at
 */
class SubscriptionPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'period_number' => $this->period_number,
            'period_amount_cents' => $this->period_amount_cents,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
