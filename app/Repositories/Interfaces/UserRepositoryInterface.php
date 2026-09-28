<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    // ==============================
    // Create Operations
    // ==============================

    /**
     * Create a new user from an attribute array.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    // ==============================
    // Read Operations
    // ==============================

    /**
     * Find a user by primary key, or null.
     */
    public function find(int $id): ?User;

    /**
     * Find a user by email address, or null.
     */
    public function findByEmail(string $email): ?User;

    // ==============================
    // Update Operations
    // ==============================

    /**
     * Update an existing user record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User;

    // ==============================
    // Delete Operations
    // ==============================

    /**
     * Permanently delete a user record.
     */
    public function delete(User $user): void;

    // ==============================
    // Get Operations
    // ==============================

    /**
     * Retrieve a paginated list of instructors.
     */
    public function getInstructorsPaginated(int $perPage = 15): LengthAwarePaginator;

    /**
     * Stream instructors in id-ordered chunks to keep memory bounded.
     *
     * @param  callable(Collection<int, User>): void  $callback
     */
    public function chunkInstructorsById(int $chunkSize, callable $callback): void;
}
