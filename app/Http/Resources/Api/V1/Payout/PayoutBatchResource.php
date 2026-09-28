<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Payout;

use App\Enums\PayoutBatchStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $batch_uuid
 * @property PayoutBatchStatus $status
 * @property int $total_cents
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PayoutBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_uuid' => $this->batch_uuid,
            'status' => $this->status?->value,
            'total_cents' => $this->total_cents,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'payouts' => PayoutResource::collection($this->whenLoaded('payouts')),
        ];
    }
}
