<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Plain text here — the User model's `hashed` cast will hash it.
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'role' => UserRole::BUSINESS_CUSTOMER,
            'business_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMIN,
        ]);
    }

    /**
     * Indicate that the user is a business customer.
     */
    public function businessCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::BUSINESS_CUSTOMER,
        ]);
    }

    /**
     * Associate the user with a business (creating one if not given).
     */
    public function forBusiness(?Business $business = null): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => $business?->id ?? Business::factory(),
        ]);
    }
}