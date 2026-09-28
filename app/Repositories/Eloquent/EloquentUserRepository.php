<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new user from an attribute array.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a user by primary key, or null.
     */
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find a user by email address, or null.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update an existing user record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->fresh() ?? $user;
    }

    // ==============================
    // Delete Operations
    // ==============================

    /**
     * Permanently delete a user record.
     */
    public function delete(User $user): void
    {
        $user->delete();
    }

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Retrieve a paginated list of instructors.
     */
    public function getInstructorsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return User::where('is_instructor', true)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Stream instructors in id-ordered chunks to keep memory bounded.
     *
     * @param  callable(Collection<int, User>): void  $callback
     */
    public function chunkInstructorsById(int $chunkSize, callable $callback): void
    {
        User::query()
            ->where('is_instructor', true)
            ->chunkById($chunkSize, $callback);
    }
}
