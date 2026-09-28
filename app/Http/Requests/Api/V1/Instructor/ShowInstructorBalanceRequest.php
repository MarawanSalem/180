<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Instructor;

use App\Exceptions\UserException;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

final class ShowInstructorBalanceRequest extends FormRequest
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
        return [];
    }
}
