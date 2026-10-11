<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'name'                    => fake()->company(),
            'phone'                   => fake()->optional()->phoneNumber(),
            'vat_number'              => fake()->unique()->numerify('VAT######'),
            'commercial_registration' => fake()->unique()->numerify('CR######'),
        ];
    }
}