<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Ledger;

use App\Enums\LedgerEntryType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $instructor_id
 * @property int|null $payout_id
 * @property int|null $subscription_period_id
 * @property LedgerEntryType $type
 * @property int $amount_cents
 * @property string $idempotency_key
 * @property array|null $metadata
 * @property Carbon $created_at
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instructor_id' => $this->instructor_id,
            'payout_id' => $this->payout_id,
            'subscription_period_id' => $this->subscription_period_id,
            'type' => $this->type?->value,
            'amount_cents' => $this->amount_cents,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
