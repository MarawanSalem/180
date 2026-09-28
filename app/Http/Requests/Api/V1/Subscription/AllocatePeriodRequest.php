<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Subscription;

use Illuminate\Foundation\Http\FormRequest;

final class AllocatePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('allocate', $this->route('period')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * Shared validation rules for API FormRequest and Filament actions.
     *
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'consumption_weights' => ['required', 'array', 'min:1'],
            'consumption_weights.*.instructor_id' => ['required', 'integer', 'exists:users,id'],
            'consumption_weights.*.weight' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
