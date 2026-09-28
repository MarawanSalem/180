<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Instructor;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon $created_at
 */
class InstructorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_instructor' => true,
            'balance_cents' => $this->when(
                $this->relationLoaded('ledgerEntries') || isset($this->balance_cents),
                fn () => $this->balance_cents,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
