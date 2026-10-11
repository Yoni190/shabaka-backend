<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\HasAuthEndpoints;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase, HasAuthEndpoints;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->postJson($this->adminLoginEndpoint(), [
            'email'    => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.email', $admin->email);
    }

    public function test_admin_login_fails_with_invalid_password(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->postJson($this->adminLoginEndpoint(), [
            'email'    => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_login_fails_for_non_admin_user(): void
    {
        User::factory()->businessCustomer()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson($this->adminLoginEndpoint(), [
            'email'    => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_login_requires_email_and_password(): void
    {
        $response = $this->postJson($this->adminLoginEndpoint(), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}