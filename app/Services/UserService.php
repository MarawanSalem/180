<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\RegisterDTO;
use App\Enums\RoleName;
use App\Exceptions\UserException;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Register a new user account and assign the matching Spatie role.
     *
     * @throws UserException when email is already taken.
     */
    public function register(RegisterDTO $dto): User
    {
        $existing = $this->users->findByEmail($dto->email);

        if ($existing !== null) {
            throw UserException::duplicateEmail($dto->email);
        }

        $user = $this->users->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'is_instructor' => $dto->isInstructor,
        ]);

        $user->assignRole(
            $dto->isInstructor ? RoleName::Instructor->value : RoleName::Student->value,
        );

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * Find a user by id or throw an exception.
     *
     * @throws UserException when user is not found.
     */
    public function findOrFail(int $id): User
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw UserException::notFound($id);
        }

        return $user;
    }

    /**
     * Find an instructor by id, asserting they are an instructor.
     *
     * @throws UserException when not found or not an instructor.
     */
    public function findInstructorOrFail(int $id): User
    {
        $user = $this->findOrFail($id);

        if (! $user->is_instructor) {
            throw UserException::notInstructor($id);
        }

        return $user;
    }

    /**
     * Return a paginated list of instructors.
     */
    public function listInstructors(int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->getInstructorsPaginated($perPage);
    }

    /**
     * Stream instructors in id-ordered chunks to keep memory bounded.
     *
     * @param  callable(Collection<int, User>): void  $callback
     */
    public function chunkInstructorsById(int $chunkSize, callable $callback): void
    {
        $this->users->chunkInstructorsById($chunkSize, $callback);
    }
}
