<?php

namespace Tests\Feature\Client\Address;

use App\Models\Address;
use App\Models\Business;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_business_can_have_multiple_addresses(): void
    {
        $business = Business::factory()->create();

        Address::factory()->count(3)->for($business)->create();

        $this->assertCount(3, $business->fresh()->addresses);
    }

    public function test_address_belongs_to_the_correct_business(): void
    {
        $business = Business::factory()->create();
        $other    = Business::factory()->create();

        $address = Address::factory()->for($business)->create();

        $this->assertTrue($address->business->is($business));
        $this->assertFalse($address->business->is($other));
    }

    public function test_business_addresses_returns_only_its_own_addresses(): void
    {
        $mine  = Business::factory()->create();
        $other = Business::factory()->create();

        Address::factory()->count(2)->for($mine)->create();
        Address::factory()->count(4)->for($other)->create();

        $this->assertCount(2, $mine->addresses);
        $this->assertCount(4, $other->addresses);
    }

    public function test_required_fields_are_persisted(): void
    {
        $address = Address::factory()->create([
            'address_line_1' => '1 Network Way',
            'city'           => 'San Jose',
            'country_code'   => 'US',
        ]);

        $this->assertDatabaseHas('addresses', [
            'id'             => $address->id,
            'address_line_1' => '1 Network Way',
            'city'           => 'San Jose',
            'country_code'   => 'US',
        ]);
    }

    public function test_nullable_fields_default_to_null_when_omitted(): void
    {
        $address = Address::factory()->create([
            'label'           => null,
            'recipient_name'  => null,
            'company_name'    => null,
            'phone'           => null,
            'address_line_2'  => null,
            'state_or_region' => null,
            'postal_code'     => null,
        ]);

        $this->assertNull($address->label);
        $this->assertNull($address->recipient_name);
        $this->assertNull($address->company_name);
        $this->assertNull($address->phone);
        $this->assertNull($address->address_line_2);
        $this->assertNull($address->state_or_region);
        $this->assertNull($address->postal_code);
    }

    public function test_booleans_persist_correctly_for_shipping_only(): void
    {
        $address = Address::factory()->create([
            'is_shipping' => true,
            'is_billing'  => false,
        ]);

        $this->assertTrue($address->fresh()->is_shipping);
        $this->assertFalse($address->fresh()->is_billing);
    }

    public function test_booleans_persist_correctly_for_billing_only(): void
    {
        $address = Address::factory()->billingOnly()->create();

        $this->assertFalse($address->fresh()->is_shipping);
        $this->assertTrue($address->fresh()->is_billing);
    }

    public function test_address_can_be_both_shipping_and_billing(): void
    {
        $address = Address::factory()->shippingAndBilling()->create();

        $this->assertTrue($address->fresh()->is_shipping);
        $this->assertTrue($address->fresh()->is_billing);
    }

    public function test_address_can_be_created_through_the_business_relationship(): void
    {
        $business = Business::factory()->create();

        $address = $business->addresses()->create([
            'address_line_1' => '1 Network Way',
            'city'           => 'San Jose',
            'country_code'   => 'US',
        ]);

        $this->assertSame($business->id, $address->business_id);
        $this->assertDatabaseHas('addresses', [
            'id'          => $address->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_foreign_key_rejects_invalid_business_id(): void
    {
        $this->expectException(QueryException::class);

        Address::create([
            'business_id'    => 99999,
            'address_line_1' => '1 Nowhere',
            'city'           => 'Nowhere',
            'country_code'   => 'US',
        ]);
    }

    public function test_business_deletion_is_restricted_while_addresses_exist(): void
    {
        $business = Business::factory()->create();
        Address::factory()->for($business)->create();

        $this->expectException(QueryException::class);

        $business->delete();
    }

    public function test_business_deletion_succeeds_after_addresses_are_removed(): void
    {
        $business = Business::factory()->create();
        Address::factory()->for($business)->create();

        $business->addresses()->delete();
        $business->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
    }
}