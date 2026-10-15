<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/orders';

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessWithUser(): array
    {
        $business = Business::factory()->create(['name' => 'Acme Networks']);
        $user     = User::factory()->create([
            'business_id' => $business->id,
            'name'        => 'Jane Buyer',
            'email'       => 'jane@acme.example',
        ]);

        return [$business, $user];
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $order = Order::factory()->create();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->getJson(self::ENDPOINT . "/{$order->id}")->assertUnauthorized();
        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'confirmed'])
            ->assertUnauthorized();
    }

    public function test_business_customer_cannot_access_admin_order_endpoints(): void
    {
        $business = Business::factory()->create();
        $customer = User::factory()->create(['business_id' => $business->id]);
        $order    = Order::factory()->for($business)->create();

        Sanctum::actingAs($customer);

        $this->getJson(self::ENDPOINT)->assertForbidden();
        $this->getJson(self::ENDPOINT . "/{$order->id}")->assertForbidden();
        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

    public function test_orders_are_listed_newest_first(): void
    {
        $older = Order::factory()->create(['created_at' => now()->subDay()]);
        $newer = Order::factory()->create(['created_at' => now()]);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $this->assertSame(
            [$newer->id, $older->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_list_is_paginated(): void
    {
        Order::factory()->count(25)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=10')->assertOk();

        $response->assertJsonCount(10, 'data')
                 ->assertJsonPath('meta.per_page', 10)
                 ->assertJsonPath('meta.total', 25)
                 ->assertJsonPath('meta.last_page', 3);
    }

    public function test_list_caps_per_page_at_100(): void
    {
        $this->getJson(self::ENDPOINT . '?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_list_includes_business_and_submitting_user(): void
    {
        [$business, $user] = $this->makeBusinessWithUser();
        Order::factory()->for($business)->create(['user_id' => $user->id]);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $response->assertJsonPath('data.0.business_name', 'Acme Networks')
                 ->assertJsonPath('data.0.user_email', 'jane@acme.example');
    }

    public function test_list_filters_by_status(): void
    {
        Order::factory()->count(2)->create(['status' => OrderStatus::PENDING]);
        Order::factory()->count(3)->create(['status' => OrderStatus::SHIPPED]);

        $this->getJson(self::ENDPOINT . '?status=shipped')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_rejects_invalid_status_filter(): void
    {
        $this->getJson(self::ENDPOINT . '?status=not-a-status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_list_filters_by_payment_status(): void
    {
        Order::factory()->count(4)->create(['payment_status' => PaymentStatus::PAID]);
        Order::factory()->count(2)->create(['payment_status' => PaymentStatus::UNPAID]);

        $this->getJson(self::ENDPOINT . '?payment_status=paid')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_list_searches_by_order_number(): void
    {
        Order::factory()->create(['order_number' => 'ORD-20260101-AAAAAAAA']);
        Order::factory()->create(['order_number' => 'ORD-20260101-BBBBBBBB']);

        $this->getJson(self::ENDPOINT . '?search=AAAA')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_searches_by_business_name(): void
    {
        $match = Business::factory()->create(['name' => 'Acme Networks']);
        $other = Business::factory()->create(['name' => 'Globex']);

        Order::factory()->for($match)->create();
        Order::factory()->for($other)->create();

        $this->getJson(self::ENDPOINT . '?search=Acme')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_show_returns_complete_order_details(): void
    {
        [$business, $user] = $this->makeBusinessWithUser();

        $order = Order::factory()->for($business)->create([
            'user_id'                   => $user->id,
            'order_number'              => 'ORD-20260101-TESTTEST',
            'subtotal'                  => '500.00',
            'total_amount'              => '500.00',
            'shipping_address_snapshot' => ['city' => 'San Jose', 'country_code' => 'US'],
            'notes'                     => 'Deliver by Friday',
        ]);

        $product = Product::factory()->create([
            'name' => 'Catalyst 9200',
            'sku'  => 'C9200-24T',
        ]);

        OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => 'Catalyst 9200',
            'sku'          => 'C9200-24T',
            'quantity'     => 2,
            'unit_price'   => '250.00',
            'subtotal'     => '500.00',
        ]);

        $response = $this->getJson(self::ENDPOINT . "/{$order->id}")->assertOk();

        $response->assertJsonPath('data.order_number', 'ORD-20260101-TESTTEST')
                 ->assertJsonPath('data.business.name', 'Acme Networks')
                 ->assertJsonPath('data.user.email', 'jane@acme.example')
                 ->assertJsonPath('data.items.0.product_name', 'Catalyst 9200')
                 ->assertJsonPath('data.items.0.sku', 'C9200-24T')
                 ->assertJsonPath('data.items.0.quantity', 2)
                 ->assertJsonPath('data.items.0.unit_price', '250.00')
                 ->assertJsonPath('data.items.0.subtotal', '500.00')
                 ->assertJsonPath('data.shipping_address.city', 'San Jose')
                 ->assertJsonPath('data.notes', 'Deliver by Friday');
    }

    public function test_show_uses_historical_snapshots_not_current_product_data(): void
    {
        $business = Business::factory()->create();
        $order    = Order::factory()->for($business)->create();

        $product = Product::factory()->create(['name' => 'Original', 'sku' => 'SKU-1']);

        OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => 'Original',
            'sku'          => 'SKU-1',
            'unit_price'   => '100.00',
            'quantity'     => 1,
            'subtotal'     => '100.00',
        ]);

        // Product changed since the order was placed.
        $product->update(['name' => 'Renamed', 'sku' => 'SKU-NEW']);

        $response = $this->getJson(self::ENDPOINT . "/{$order->id}")->assertOk();

        $response->assertJsonPath('data.items.0.product_name', 'Original')
                 ->assertJsonPath('data.items.0.sku', 'SKU-1')
                 ->assertJsonPath('data.items.0.unit_price', '100.00');
    }

    public function test_show_does_not_expose_password_or_tokens(): void
    {
        [$business, $user] = $this->makeBusinessWithUser();
        $order = Order::factory()->for($business)->create(['user_id' => $user->id]);

        $response = $this->getJson(self::ENDPOINT . "/{$order->id}")->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));
    }

    public function test_show_returns_404_for_missing_order(): void
    {
        $this->getJson(self::ENDPOINT . '/99999')->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update status — valid transitions
    |--------------------------------------------------------------------------
    */

    public function test_pending_can_transition_to_confirmed(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('message', 'Order status updated successfully');

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_full_happy_path_transitions(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => $status])
                ->assertOk();
        }

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }

    public function test_shipped_can_only_transition_to_delivered(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::SHIPPED]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Update status — invalid transitions
    |--------------------------------------------------------------------------
    */

    public function test_invalid_status_value_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'not-real'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_pending_cannot_jump_to_shipped(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_delivered_is_terminal(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::DELIVERED]);

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'cancelled'] as $status) {
            $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => $status])
                ->assertUnprocessable();
        }
    }

    public function test_cancelled_is_terminal(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::CANCELLED]);

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => $status])
                ->assertUnprocessable();
        }
    }

    public function test_client_cannot_modify_payment_status_via_status_endpoint(): void
    {
        $order = Order::factory()->create([
            'status'         => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
        ]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", [
            'status'         => 'confirmed',
            'payment_status' => 'paid',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['payment_status']);

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_payment_status_unchanged_by_status_update(): void
    {
        $order = Order::factory()->create([
            'status'         => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
        ]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_update_status_returns_404_for_missing_order(): void
    {
        $this->patchJson(self::ENDPOINT . '/99999/status', ['status' => 'confirmed'])
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Cancellation & stock restoration
    |--------------------------------------------------------------------------
    */

    public function test_cancelling_an_order_restores_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $order   = Order::factory()->create(['status' => OrderStatus::PROCESSING]);

        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 5]);

        // Simulate post-order state: stock was 8 before, 5 units ordered, 3 left.
        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_repeated_cancellation_cannot_restore_stock_twice(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $order   = Order::factory()->create(['status' => OrderStatus::PROCESSING]);

        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 5]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(8, $product->fresh()->stock_quantity);

        // Second attempt is blocked by the terminal state, and even if it
        // weren't, stock_restored_at would prevent a second restoration.
        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable();

        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_restores_multiple_products(): void
    {
        $p1 = Product::factory()->create(['stock_quantity' => 1]);
        $p2 = Product::factory()->create(['stock_quantity' => 5]);

        $order = Order::factory()->create(['status' => OrderStatus::CONFIRMED]);

        OrderItem::factory()->for($order)->for($p1)->create(['quantity' => 4]);
        OrderItem::factory()->for($order)->for($p2)->create(['quantity' => 2]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(5, $p1->fresh()->stock_quantity);
        $this->assertSame(7, $p2->fresh()->stock_quantity);
    }

    public function test_cancelling_order_with_deleted_product_does_not_error(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $order   = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $item    = OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        $product->delete();

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        // Order item's snapshot remains even though product_id was nulled.
        $this->assertSame(3, $item->fresh()->quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_non_cancellation_transitions_do_not_restore_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $order   = Order::factory()->create(['status' => OrderStatus::PENDING]);

        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 5]);

        $this->patchJson(self::ENDPOINT . "/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }
}