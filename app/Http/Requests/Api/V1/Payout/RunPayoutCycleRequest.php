<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payout;

use App\Models\Payout;
use Illuminate\Foundation\Http\FormRequest;

final class RunPayoutCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('run', Payout::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
