<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Authorization\PermissionName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorAuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_list_instructors(): void
    {
        Sanctum::actingAs(User::factory()->student()->create());

        $this->getJson('/api/v1/instructors')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_instructor_can_view_own_ledger_and_balance(): void
    {
        $instructor = User::factory()->instructor()->create();

        Sanctum::actingAs($instructor);

        $this->getJson("/api/v1/instructors/{$instructor->id}/ledger")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/v1/instructors/{$instructor->id}/balance")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_instructor_without_ledger_view_cannot_read_ledger(): void
    {
        $instructor = User::factory()->instructor()->create();
        $instructor->syncRoles([]);
        $instructor->syncPermissions([
            PermissionName::InstructorsView,
            PermissionName::PayoutsView,
        ]);

        Sanctum::actingAs($instructor);

        $this->getJson("/api/v1/instructors/{$instructor->id}/ledger")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_instructor_cannot_view_another_instructors_payouts(): void
    {
        $self = User::factory()->instructor()->create();
        $other = User::factory()->instructor()->create();

        Sanctum::actingAs($self);

        $this->getJson("/api/v1/instructors/{$other->id}/payouts")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_missing_instructor_on_payouts_returns_user_not_found(): void
    {
        Sanctum::actingAs(User::factory()->instructor()->create());

        $this->getJson('/api/v1/instructors/999999/payouts')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'user.not_found');
    }

    public function test_register_response_includes_roles_and_permissions(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Role Check',
            'email' => 'roles@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_instructor' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'instructor')
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'roles',
                    'permissions',
                    'token',
                ],
            ]);
    }
}
