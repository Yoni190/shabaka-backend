<?php

namespace Tests\Feature\Client;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_own_order_with_snapshots(): void
    {
        $business = Business::factory()->create();
        $user     = User::factory()->create(['business_id' => $business->id]);
        $order    = Order::factory()->for($business)->create([
            'shipping_address_snapshot' => ['city' => 'San Jose', 'country_code' => 'US'],
            'currency'                  => 'SAR',
        ]);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Original Name',
            'sku'          => 'SKU-1',
            'unit_price'   => '100.00',
            'quantity'     => 2,
            'subtotal'     => '200.00',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.shipping_address.city', 'San Jose')
            ->assertJsonPath('data.items.0.product_name', 'Original Name')
            ->assertJsonPath('data.items.0.sku', 'SKU-1');
    }

    public function test_customer_cannot_view_another_business_order(): void
    {
        $mine     = Business::factory()->create();
        $user     = User::factory()->create(['business_id' => $mine->id]);
        $other    = Business::factory()->create();
        $foreign  = Order::factory()->for($other)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$foreign->id}")->assertNotFound();
    }

    public function test_user_without_business_is_forbidden(): void
    {
        $user = User::factory()->create(['business_id' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders/1')->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/orders/1')->assertUnauthorized();
    }
}