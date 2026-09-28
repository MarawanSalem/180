<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Subscription;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

final class IndexSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Subscription::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
