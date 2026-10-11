<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Auth\Concerns\HasAuthEndpoints;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase, HasAuthEndpoints;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->businessCustomer()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson($this->logoutEndpoint());

        $response->assertOk()
            ->assertJson(['message' => 'Logged out']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user  = User::factory()->businessCustomer()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->logoutEndpoint())
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson($this->logoutEndpoint());

        $response->assertUnauthorized();
    }
}