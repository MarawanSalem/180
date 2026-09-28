<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Subscription;

use App\Enums\PlanTier;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Subscription::class) ?? false;
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
            'plan_tier' => ['required', Rule::enum(PlanTier::class)],
            'price_cents' => ['required', 'integer', 'min:1'],
            'platform_fee_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'starts_at' => ['required', 'date'],
        ];
    }
}
