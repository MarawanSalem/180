<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Subscription;

use App\Enums\PlanTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpgradeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upgrade', $this->route('subscription')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_tier' => ['required', Rule::enum(PlanTier::class)],
            'new_price_cents' => ['required', 'integer', 'min:1'],
            'upgrade_date' => ['sometimes', 'date'],
            'consumption_weights' => ['sometimes', 'array'],
            'consumption_weights.*.instructor_id' => ['required_with:consumption_weights', 'integer', 'exists:users,id'],
            'consumption_weights.*.weight' => ['required_with:consumption_weights', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
