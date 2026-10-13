<?php

namespace Tests\Feature\Client\Address;

use App\Models\Address;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/addresses';

    /**
     * @return array{0: User, 1: Business}
     */
    private function customerWithBusiness(): array
    {
        $business = Business::factory()->create();
        $user     = User::factory()->create(['business_id' => $business->id]);

        return [$user, $business];
    }

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

     /*
    |--------------------------------------------------------------------------
    | Authentication & authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $address = Address::factory()->create();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->postJson(self::ENDPOINT, [])->assertUnauthorized();
        $this->getJson(self::ENDPOINT . "/{$address->id}")->assertUnauthorized();
        $this->putJson(self::ENDPOINT . "/{$address->id}", [])->assertUnauthorized();
        $this->patchJson(self::ENDPOINT . "/{$address->id}", [])->assertUnauthorized();
        $this->deleteJson(self::ENDPOINT . "/{$address->id}")->assertUnauthorized();
    }

    public function test_user_without_business_is_forbidden(): void
    {
        $user = User::factory()->create(['business_id' => null]);
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)->assertForbidden();
        $this->postJson(self::ENDPOINT, [
            'address_line_1' => 'X',
            'city'           => 'Y',
            'country_code'   => 'US',
        ])->assertForbidden();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_admin_cannot_list_addresses(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(self::ENDPOINT)->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

    public function test_customer_lists_only_their_own_business_addresses(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Address::factory()->count(3)->for($business)->create();

        $otherBusiness = Business::factory()->create();
        Address::factory()->count(5)->for($otherBusiness)->create();

        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_returns_empty_array_when_no_addresses(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('data', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_customer_creates_an_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $response = $this->postJson(self::ENDPOINT, [
            'label'           => 'Head Office',
            'recipient_name'  => 'Jane Doe',
            'phone'           => '+1-555-0100',
            'address_line_1'  => '1 Network Way',
            'city'            => 'San Jose',
            'state_or_region' => 'CA',
            'postal_code'     => '95110',
            'country_code'    => 'US',
            'is_shipping'     => true,
            'is_billing'      => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Head Office')
            ->assertJsonPath('data.is_shipping', true)
            ->assertJsonPath('data.is_billing', true)
            ->assertJsonPath('message', 'Address created successfully');

        $this->assertDatabaseHas('addresses', [
            'business_id'    => $business->id,
            'address_line_1' => '1 Network Way',
        ]);
    }

    public function test_created_address_is_associated_with_authenticated_business(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'address_line_1' => '1 Network Way',
            'city'           => 'San Jose',
            'country_code'   => 'US',
        ])->assertCreated();

        $this->assertSame(1, $business->addresses()->count());
    }

    public function test_client_supplied_business_id_is_prohibited(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $otherBusiness = Business::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'business_id'    => $otherBusiness->id,
            'address_line_1' => '1 Network Way',
            'city'           => 'San Jose',
            'country_code'   => 'US',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['business_id']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_creation_requires_mandatory_fields(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_line_1', 'city', 'country_code']);
    }

    public function test_creation_rejects_invalid_country_code(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'address_line_1' => 'X',
            'city'           => 'Y',
            'country_code'   => 'USA',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['country_code']);
    }

    public function test_creation_rejects_non_boolean_flags(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'address_line_1' => 'X',
            'city'           => 'Y',
            'country_code'   => 'US',
            'is_shipping'    => 'yes please',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['is_shipping']);
    }

    public function test_creation_accepts_omitted_nullable_fields(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'address_line_1' => '1 Network Way',
            'city'           => 'San Jose',
            'country_code'   => 'US',
        ])->assertCreated()
          ->assertJsonPath('data.label', null)
          ->assertJsonPath('data.recipient_name', null)
          ->assertJsonPath('data.address_line_2', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_customer_shows_own_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $address = Address::factory()->for($business)->create();
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT . "/{$address->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $address->id);
    }

    public function test_customer_cannot_show_another_business_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $otherBusiness = Business::factory()->create();
        $otherAddress = Address::factory()->for($otherBusiness)->create();
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT . "/{$otherAddress->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_missing_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT . '/99999')->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_customer_updates_own_address_with_put(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $address = Address::factory()->for($business)->create(['city' => 'Old City']);
        Sanctum::actingAs($user);

        $this->putJson(self::ENDPOINT . "/{$address->id}", [
            'city' => 'New City',
        ])->assertOk()
          ->assertJsonPath('data.city', 'New City')
          ->assertJsonPath('message', 'Address updated successfully');

        $this->assertDatabaseHas('addresses', [
            'id'   => $address->id,
            'city' => 'New City',
        ]);
    }

    public function test_customer_patches_address_partially(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $address = Address::factory()->for($business)->create([
            'city'        => 'Old City',
            'postal_code' => '11111',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson(self::ENDPOINT . "/{$address->id}", [
            'postal_code' => '22222',
        ])->assertOk();

        $address->refresh();
        $this->assertSame('Old City', $address->city);
        $this->assertSame('22222', $address->postal_code);
    }

    public function test_customer_cannot_update_another_business_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $otherBusiness = Business::factory()->create();
        $otherAddress  = Address::factory()->for($otherBusiness)->create(['city' => 'Original']);
        Sanctum::actingAs($user);

        $this->putJson(self::ENDPOINT . "/{$otherAddress->id}", [
            'city' => 'Hijacked',
        ])->assertNotFound();

        $this->assertSame('Original', $otherAddress->fresh()->city);
    }

    public function test_update_rejects_invalid_payload(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $address = Address::factory()->for($business)->create();
        Sanctum::actingAs($user);

        $this->patchJson(self::ENDPOINT . "/{$address->id}", [
            'country_code' => 'USA',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['country_code']);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_customer_deletes_own_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $address = Address::factory()->for($business)->create();
        Sanctum::actingAs($user);

        $this->deleteJson(self::ENDPOINT . "/{$address->id}")
            ->assertOk()
            ->assertJson(['message' => 'Address deleted successfully']);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_customer_cannot_delete_another_business_address(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $otherBusiness = Business::factory()->create();
        $otherAddress  = Address::factory()->for($otherBusiness)->create();
        Sanctum::actingAs($user);

        $this->deleteJson(self::ENDPOINT . "/{$otherAddress->id}")->assertNotFound();

        $this->assertDatabaseHas('addresses', ['id' => $otherAddress->id]);
    }

    public function test_delete_does_not_affect_unrelated_records(): void
    {
        [$user, $business] = $this->customerWithBusiness();
        $mine  = Address::factory()->for($business)->create();
        $other = Address::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson(self::ENDPOINT . "/{$mine->id}")->assertOk();

        $this->assertDatabaseMissing('addresses', ['id' => $mine->id]);
        $this->assertDatabaseHas('addresses', ['id' => $other->id]);
    }
}