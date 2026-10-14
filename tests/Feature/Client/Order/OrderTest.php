<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/orders';

    /**
     * @return array{0: User, 1: Business}
     */
    private function customer(): array
    {
        $business = Business::factory()->create();
        $user     = User::factory()->create(['business_id' => $business->id]);

        return [$user, $business];
    }

    private function seedCart(Business $business, Product $product, int $quantity): Cart
    {
        $cart = Cart::factory()->for($business)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => $quantity]);

        return $cart;
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication & authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson(self::ENDPOINT, [])->assertUnauthorized();
    }

    public function test_user_without_business_is_forbidden(): void
    {
        $address = Address::factory()->create();

        Sanctum::actingAs(User::factory()->create(['business_id' => null]));

        $this->postJson(self::ENDPOINT, [
            'shipping_address_id' => $address->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Happy path
    |--------------------------------------------------------------------------
    */

    public function test_places_an_order_from_cart_with_one_item(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create([
            'name'           => 'Catalyst 9200',
            'sku'            => 'C9200-24T',
            'price'          => 2499.99,
            'stock_quantity' => 10,
        ]);
        $this->seedCart($business, $product, 2);

        Sanctum::actingAs($user);

        $response = $this->postJson(self::ENDPOINT, [
            'shipping_address_id' => $address->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.subtotal', '4999.98')
            ->assertJsonPath('data.total_amount', '4999.98')
            ->assertJsonPath('data.items.0.product_name', 'Catalyst 9200')
            ->assertJsonPath('data.items.0.sku', 'C9200-24T')
            ->assertJsonPath('data.items.0.unit_price', '2499.99')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.subtotal', '4999.98')
            ->assertJsonPath('message', 'Order placed successfully');

        $this->assertDatabaseHas('orders', [
            'business_id'    => $business->id,
            'user_id'        => $user->id,
            'status'         => OrderStatus::PENDING->value,
            'payment_status' => PaymentStatus::UNPAID->value,
        ]);
    }

    public function test_places_an_order_with_multiple_items(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();

        $cart = Cart::factory()->for($business)->create();

        $p1 = Product::factory()->create(['price' => 100.00, 'stock_quantity' => 5]);
        $p2 = Product::factory()->create(['price' => 250.50, 'stock_quantity' => 5]);
        $p3 = Product::factory()->create(['price' => 99.99,  'stock_quantity' => 5]);

        CartItem::factory()->for($cart)->for($p1)->create(['quantity' => 1]);
        CartItem::factory()->for($cart)->for($p2)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)->for($p3)->create(['quantity' => 3]);

        Sanctum::actingAs($user);

        // 100.00 + 501.00 + 299.97 = 900.97
        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '900.97')
            ->assertJsonPath('data.total_amount', '900.97');

        $this->assertSame(1, Order::count());
        $this->assertSame(3, Order::first()->items()->count());
    }

    public function test_order_snapshots_shipping_address(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create([
            'label'           => 'HQ',
            'recipient_name'  => 'Jane Doe',
            'address_line_1'  => '1 Network Way',
            'city'            => 'San Jose',
            'state_or_region' => 'CA',
            'postal_code'     => '95110',
            'country_code'    => 'US',
        ]);
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $response = $this->postJson(self::ENDPOINT, [
            'shipping_address_id' => $address->id,
        ])->assertCreated();

        $response->assertJsonPath('data.shipping_address.label', 'HQ')
                 ->assertJsonPath('data.shipping_address.address_line_1', '1 Network Way')
                 ->assertJsonPath('data.shipping_address.country_code', 'US');
    }

    public function test_snapshot_survives_address_deletion(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create([
            'address_line_1' => '1 Network Way',
        ]);
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertCreated();

        $address->delete();

        $order = Order::first();
        $this->assertNull($order->fresh()->shipping_address_id);
        $this->assertSame('1 Network Way', $order->shipping_address_snapshot['address_line_1']);
    }

    public function test_cart_is_cleared_after_successful_order(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $cart = $this->seedCart($business, $product, 2);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertCreated();

        $this->assertSame(0, $cart->fresh()->items()->count());
        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
    }

    public function test_stock_is_decremented_after_order(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->seedCart($business, $product, 3);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertCreated();

        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    /*
    |--------------------------------------------------------------------------
    | Rejections
    |--------------------------------------------------------------------------
    */

    public function test_empty_cart_cannot_create_order(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        Cart::factory()->for($business)->create();

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_no_cart_cannot_create_order(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_foreign_business_address_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $otherBusiness = Business::factory()->create();
        $foreignAddress = Address::factory()->for($otherBusiness)->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $foreignAddress->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_address_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_shipping_address_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_address_id']);
    }

    public function test_inactive_product_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['is_active' => false, 'stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $this->seedCart($business, $product, 5);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_client_supplied_totals_are_prohibited(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'shipping_address_id' => $address->id,
            'total_amount'        => '0.01',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['total_amount']);
    }

    public function test_cross_business_cart_is_not_usable(): void
    {
        [$user, $business] = $this->customer();
        $otherBusiness = Business::factory()->create();
        $address = Address::factory()->for($business)->create();

        // Other business has a full cart; authenticated user has none.
        $otherCart = Cart::factory()->for($otherBusiness)->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        CartItem::factory()->for($otherCart)->for($product)->create(['quantity' => 2]);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertDatabaseCount('orders', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Failure atomicity
    |--------------------------------------------------------------------------
    */

    public function test_failed_order_does_not_alter_cart_or_stock(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();

        $cart = Cart::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 1]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 5]);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(1, $cart->fresh()->items()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Historical data
    |--------------------------------------------------------------------------
    */

    public function test_order_item_price_remains_after_product_price_change(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['price' => 100.00, 'stock_quantity' => 5]);
        $this->seedCart($business, $product, 2);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertCreated();

        $product->update(['price' => 999.99, 'name' => 'Renamed Product']);

        $item = Order::first()->items()->first();
        $this->assertSame('100.00', $item->unit_price);
        $this->assertSame('200.00', $item->subtotal);
        $this->assertNotSame('Renamed Product', $item->product_name);
    }

    public function test_order_item_survives_product_deletion(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['name' => 'Original', 'sku' => 'SKU-1', 'stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
            ->assertCreated();

        $product->delete();

        $item = Order::first()->items()->first();
        $this->assertNull($item->product_id);
        $this->assertSame('Original', $item->product_name);
        $this->assertSame('SKU-1', $item->sku);
    }

    /*
    |--------------------------------------------------------------------------
    | Order number
    |--------------------------------------------------------------------------
    */

    public function test_order_numbers_are_unique_across_orders(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();

        for ($i = 0; $i < 5; $i++) {
            $product = Product::factory()->create(['stock_quantity' => 5]);
            $cart = Cart::where('business_id', $business->id)->first()
                ?? Cart::factory()->for($business)->create();
            CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

            Sanctum::actingAs($user);
            $this->postJson(self::ENDPOINT, ['shipping_address_id' => $address->id])
                ->assertCreated();
        }

        $this->assertSame(5, Order::distinct('order_number')->count('order_number'));
    }

    public function test_order_number_is_not_accepted_from_client(): void
    {
        [$user, $business] = $this->customer();
        $address = Address::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $this->seedCart($business, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'shipping_address_id' => $address->id,
            'order_number'        => 'ORD-FAKE',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['order_number']);
    }
}