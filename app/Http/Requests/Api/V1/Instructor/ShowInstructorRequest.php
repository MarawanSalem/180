<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Instructor;

use App\Exceptions\UserException;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ShowInstructorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $instructor = User::query()->find($this->route('instructor'));

        if ($instructor === null) {
            throw UserException::notFound((int) $this->route('instructor'));
        }

        return $this->user()?->can('viewInstructor', $instructor) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
