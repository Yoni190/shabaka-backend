<?php

namespace Tests\Feature\Client\Cart;

use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_has_one_cart(): void
    {
        $business = Business::factory()->create();
        $cart = Cart::factory()->for($business)->create();

        $this->assertTrue($business->fresh()->cart->is($cart));
    }

    public function test_business_without_cart_returns_null(): void
    {
        $business = Business::factory()->create();

        $this->assertNull($business->cart);
    }

    public function test_business_cannot_have_more_than_one_cart(): void
    {
        $business = Business::factory()->create();
        Cart::factory()->for($business)->create();

        $this->expectException(QueryException::class);

        Cart::factory()->for($business)->create();
    }

    public function test_cart_belongs_to_its_business(): void
    {
        $business = Business::factory()->create();
        $cart = Cart::factory()->for($business)->create();

        $this->assertTrue($cart->business->is($business));
    }

    public function test_cart_has_many_items(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->count(3)->for($cart)->create();

        $this->assertCount(3, $cart->fresh()->items);
    }

    public function test_cart_item_belongs_to_cart_and_product(): void
    {
        $cart    = Cart::factory()->create();
        $product = Product::factory()->create();

        $item = CartItem::factory()->for($cart)->for($product)->create();

        $this->assertTrue($item->cart->is($cart));
        $this->assertTrue($item->product->is($product));
    }

    public function test_same_product_cannot_be_added_twice_to_the_same_cart(): void
    {
        $cart    = Cart::factory()->create();
        $product = Product::factory()->create();

        CartItem::factory()->for($cart)->for($product)->create();

        $this->expectException(QueryException::class);

        CartItem::factory()->for($cart)->for($product)->create();
    }

    public function test_same_product_can_exist_in_different_carts(): void
    {
        $product = Product::factory()->create();
        $cartA   = Cart::factory()->create();
        $cartB   = Cart::factory()->create();

        CartItem::factory()->for($cartA)->for($product)->create();
        CartItem::factory()->for($cartB)->for($product)->create();

        $this->assertSame(
            2,
            CartItem::where('product_id', $product->id)->count(),
        );
    }

    public function test_deleting_a_cart_cascades_its_items(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->count(3)->for($cart)->create();

        $cart->delete();

        $this->assertSame(0, CartItem::where('cart_id', $cart->id)->count());
    }

    public function test_deleting_a_product_cascades_its_cart_items(): void
    {
        $product = Product::factory()->create();
        $item    = CartItem::factory()->for($product)->create();

        $product->delete();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }
}