<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ledger;

use App\Enums\LedgerEntryType;
use App\Exceptions\UserException;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $instructor = User::query()->find($this->route('instructor'));

        if ($instructor === null) {
            throw UserException::notFound((int) $this->route('instructor'));
        }

        $actor = $this->user();

        return $actor !== null
            && $actor->can(PermissionName::LedgerView)
            && $actor->can('viewInstructor', $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(LedgerEntryType::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
