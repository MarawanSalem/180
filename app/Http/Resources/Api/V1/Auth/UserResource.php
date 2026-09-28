<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_instructor
 * @property Carbon $created_at
 */
class UserResource extends JsonResource
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
            'is_instructor' => $this->is_instructor,
            'roles' => $this->when(
                method_exists($this->resource, 'getRoleNames'),
                fn () => $this->getRoleNames()->values()->all(),
            ),
            'permissions' => $this->when(
                method_exists($this->resource, 'getAllPermissions'),
                fn () => $this->getAllPermissions()->pluck('name')->values()->all(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
