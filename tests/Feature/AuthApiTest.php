<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Student One',
            'email' => 'student@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_instructor' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'student@example.com')
            ->assertJsonStructure(['data' => ['id', 'token']]);
    }

    public function test_guest_cannot_access_me(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_login_and_me_round_trip(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonPath('data.id', $user->id);
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'login@example.com');
    }

    public function test_non_owner_cannot_view_subscription(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $subscription = \App\Models\Subscription::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->getJson("/api/v1/subscriptions/{$subscription->id}")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_missing_subscription_returns_model_not_found_code(): void
    {
        Sanctum::actingAs(User::factory()->student()->create());

        $this->getJson('/api/v1/subscriptions/999999')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'subscription.not_found')
            ->assertJsonPath('message', 'Subscription not found.');
    }

    public function test_unknown_route_returns_generic_not_found(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }
}
