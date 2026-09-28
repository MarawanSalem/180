<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\RegisterDTO;
use App\Enums\RoleName;
use App\Exceptions\UserException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin user / role assignment operations for Filament (and future API).
 * Validation uses shared rule sets — not Filament-only field rules.
 */
class UserAdminService
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UserException
     */
    public function createUser(array $data): User
    {
        $validated = Validator::make($data, self::createRuleSet())->validate();
        $roles = $validated['roles'] ?? [];
        $isInstructor = (bool) ($validated['is_instructor'] ?? false)
            || in_array(RoleName::Instructor->value, $roles, true);

        $user = $this->users->register(new RegisterDTO(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            isInstructor: $isInstructor,
        ));

        $this->syncAccess($user, $roles, $validated['permissions'] ?? []);

        if ((bool) $user->is_instructor !== $isInstructor) {
            $user->update(['is_instructor' => $isInstructor]);
        }

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): User
    {
        $validated = Validator::make($data, self::updateRuleSet($user))->validate();

        return DB::transaction(function () use ($user, $validated): User {
            $roles = $validated['roles'] ?? [];
            $isInstructor = (bool) ($validated['is_instructor'] ?? false)
                || in_array(RoleName::Instructor->value, $roles, true);

            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_instructor' => $isInstructor,
            ];

            if (! empty($validated['password'])) {
                $attributes['password'] = $validated['password'];
            }

            $user->update($attributes);
            $this->syncAccess($user, $roles, $validated['permissions'] ?? []);

            return $user->fresh(['roles', 'permissions']) ?? $user;
        });
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function syncAccess(User $user, array $roles, array $permissions = []): void
    {
        $user->syncRoles($roles);
        $user->syncPermissions($permissions);
    }

    /**
     * Sync a Spatie role's permissions (Filament Roles & permissions).
     *
     * @param  array<string, mixed>  $data
     */
    public function syncRolePermissions(Role $role, array $data): Role
    {
        $validated = Validator::make($data, self::rolePermissionRuleSet())->validate();

        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh('permissions') ?? $role;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rolePermissionRuleSet(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createRuleSet(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'is_instructor' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRuleSet(User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'is_instructor' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'name')->all();
    }

    /**
     * @return array<string, string>
     */
    public function permissionOptions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }
}
