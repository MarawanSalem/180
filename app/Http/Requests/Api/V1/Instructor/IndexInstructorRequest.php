<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Instructor;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class IndexInstructorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAnyInstructors', User::class) ?? false;
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
