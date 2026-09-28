<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Support\Authorization\PermissionName;
use App\Support\Authorization\RolePermissionMap;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionName::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RolePermissionMap::map() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        // Ensure enum cases stay in sync with seeded role rows.
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
