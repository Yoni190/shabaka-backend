<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\Concerns\HasAuthEndpoints;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase, HasAuthEndpoints;

    public function test_business_can_register(): void
    {
        $response = $this->postJson($this->registerEndpoint(), [
            'name'                    => 'John Doe',
            'email'                   => 'john@example.com',
            'password'                => 'password',
            'password_confirmation'   => 'password',
            'business_name'           => 'Acme Inc',
            'phone'                   => '1234567890',
            'vat_number'              => 'VAT123',
            'commercial_registration' => 'CR123',
        ]);

        $response->assertCreated()
            ->assertJson(['message' => 'Business account created successfully']);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role'  => UserRole::BUSINESS_CUSTOMER,
        ]);

        $this->assertDatabaseHas('businesses', [
            'name'       => 'Acme Inc',
            'vat_number' => 'VAT123',
        ]);
    }

    public function test_registration_requires_fields(): void
    {
        $response = $this->postJson($this->registerEndpoint(), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name', 'email', 'password', 'business_name',
            ]);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->businessCustomer()->create(['email' => 'john@example.com']);

        $response = $this->postJson($this->registerEndpoint(), [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'business_name'         => 'Acme Inc',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_duplicate_vat_number(): void
    {
        Business::factory()->create(['vat_number' => 'VAT123']);

        $response = $this->postJson($this->registerEndpoint(), [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'business_name'         => 'Acme Inc',
            'vat_number'            => 'VAT123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['vat_number']);
    }

    public function test_registration_fails_with_duplicate_commercial_registration(): void
    {
        Business::factory()->create(['commercial_registration' => 'CR123']);

        $response = $this->postJson($this->registerEndpoint(), [
            'name'                    => 'John Doe',
            'email'                   => 'john@example.com',
            'password'                => 'password',
            'password_confirmation'   => 'password',
            'business_name'           => 'Acme Inc',
            'commercial_registration' => 'CR123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['commercial_registration']);
    }

    public function test_password_must_be_confirmed(): void
    {
        $response = $this->postJson($this->registerEndpoint(), [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password',
            'password_confirmation' => 'different',
            'business_name'         => 'Acme Inc',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_min_length(): void
    {
        $response = $this->postJson($this->registerEndpoint(), [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
            'business_name'         => 'Acme Inc',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}