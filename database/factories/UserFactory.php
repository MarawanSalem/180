<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_instructor' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if (! Schema::hasTable('roles')) {
                return;
            }

            $role = $user->is_instructor ? RoleName::Instructor : RoleName::Student;

            if (Role::query()->where('name', $role->value)->exists()) {
                $user->assignRole($role->value);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function instructor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_instructor' => true,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_instructor' => false,
        ]);
    }
}
