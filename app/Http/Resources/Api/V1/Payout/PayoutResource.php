<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Payout;

use App\Enums\PayoutStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $payout_batch_id
 * @property int $instructor_id
 * @property int $amount_cents
 * @property PayoutStatus $status
 * @property string $idempotency_key
 * @property string|null $provider_tx_id
 * @property string|null $failure_reason
 * @property int $attempts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payout_batch_id' => $this->payout_batch_id,
            'instructor_id' => $this->instructor_id,
            'amount_cents' => $this->amount_cents,
            'status' => $this->status?->value,
            'idempotency_key' => $this->idempotency_key,
            'provider_tx_id' => $this->provider_tx_id,
            'failure_reason' => $this->failure_reason,
            'attempts' => $this->attempts,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
