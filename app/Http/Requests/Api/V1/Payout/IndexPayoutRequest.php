<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payout;

use App\Enums\PayoutStatus;
use App\Exceptions\UserException;
use App\Models\Payout;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $instructorId = $this->route('instructor');

        if ($instructorId !== null) {
            $instructor = User::query()->find($instructorId);

            if ($instructor === null) {
                throw UserException::notFound((int) $instructorId);
            }

            $actor = $this->user();

            if ($actor === null || ! $actor->can('viewInstructor', $instructor)) {
                return false;
            }

            if ($actor->can(PermissionName::PayoutsViewAny)) {
                return true;
            }

            return $actor->can(PermissionName::PayoutsView)
                && (int) $actor->id === (int) $instructor->id;
        }

        return $this->user()?->can('viewAny', Payout::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::enum(PayoutStatus::class)],
            'instructor_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
