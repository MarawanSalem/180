<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\PermissionName;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::RolesViewAny);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesView);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesUpdate);
    }
}
