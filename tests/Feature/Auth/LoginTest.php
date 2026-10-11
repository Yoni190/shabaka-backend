<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\HasAuthEndpoints;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, HasAuthEndpoints;

    public function test_business_customer_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->businessCustomer()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson($this->loginEndpoint(), [
            'email'    => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->businessCustomer()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson($this->loginEndpoint(), [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_admin_user(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->postJson($this->loginEndpoint(), [
            'email'    => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson($this->loginEndpoint(), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}