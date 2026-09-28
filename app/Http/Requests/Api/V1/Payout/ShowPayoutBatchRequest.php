<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payout;

use Illuminate\Foundation\Http\FormRequest;

final class ShowPayoutBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('payoutBatch')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
