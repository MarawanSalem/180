<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\PermissionName;

/**
 * Covers admin user management and instructor profile authorization
 * (both use the User model).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UsersViewAny);
    }

    /**
     * List instructors (API instructors index).
     */
    public function viewAnyInstructors(User $user): bool
    {
        return $user->can(PermissionName::InstructorsViewAny);
    }

    public function view(User $actor, User $model): bool
    {
        if ($actor->can(PermissionName::UsersView)) {
            return true;
        }

        if (! $model->is_instructor) {
            return false;
        }

        return $this->viewInstructor($actor, $model);
    }

    /**
     * View an instructor profile (requires instructor flag + instructor view perms).
     */
    public function viewInstructor(User $actor, User $instructor): bool
    {
        if (! $instructor->is_instructor) {
            return false;
        }

        if ($actor->can(PermissionName::UsersView) || $actor->can(PermissionName::InstructorsViewAny)) {
            return true;
        }

        return $actor->can(PermissionName::InstructorsView)
            && (int) $actor->id === (int) $instructor->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::UsersCreate);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionName::UsersUpdate);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(PermissionName::UsersDelete)
            && (int) $user->id !== (int) $model->id;
    }
}
