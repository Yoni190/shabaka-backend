<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id'     => Business::factory(),
            'label'           => null,
            'recipient_name'  => $this->faker->name(),
            'company_name'    => null,
            'phone'           => $this->faker->numerify('+966-##-###-####'),
            'address_line_1'  => $this->faker->streetAddress(),
            'address_line_2'  => null,
            'city'            => $this->faker->city(),
            'state_or_region' => $this->faker->stateAbbr(),
            'postal_code'     => $this->faker->postcode(),
            'country_code'    => 'SA',
            'is_shipping'     => true,
            'is_billing'      => false,
        ];
    }

    public function billingOnly(): static
    {
        return $this->state(fn () => [
            'is_shipping' => false,
            'is_billing'  => true,
        ]);
    }

    public function shippingAndBilling(): static
    {
        return $this->state(fn () => [
            'is_shipping' => true,
            'is_billing'  => true,
        ]);
    }
}