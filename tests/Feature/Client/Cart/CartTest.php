<?php

namespace Tests\Feature\Client\Cart;

use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private const CART  = '/api/v1/cart';
    private const ITEMS = '/api/v1/cart/items';

    /**
     * @return array{0: User, 1: Business}
     */
    private function customer(): array
    {
        $business = Business::factory()->create();
        $user     = User::factory()->create(['business_id' => $business->id]);

        return [$user, $business];
    }

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

     /*
    |--------------------------------------------------------------------------
    | Authentication & authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson(self::CART)->assertUnauthorized();
        $this->postJson(self::ITEMS, [])->assertUnauthorized();
        $this->patchJson(self::ITEMS . '/1', [])->assertUnauthorized();
        $this->deleteJson(self::ITEMS . '/1')->assertUnauthorized();
        $this->deleteJson(self::ITEMS)->assertUnauthorized();
    }

    public function test_user_without_business_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['business_id' => null]));

        $this->getJson(self::CART)->assertForbidden();
        $this->postJson(self::ITEMS, [
            'product_id' => Product::factory()->create()->id,
            'quantity'   => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_admin_cannot_access_cart(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(self::CART)->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_showing_empty_cart_returns_empty_items_and_zero_subtotal(): void
    {
        [$user] = $this->customer();
        Sanctum::actingAs($user);

        $this->getJson(self::CART)
            ->assertCreated()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.subtotal', '0.00');
    }

    public function test_show_returns_items_with_product_details_and_subtotal(): void
    {
        [$user, $business] = $this->customer();
        $cart = Cart::factory()->for($business)->create();

        $product = Product::factory()->create([
            'name'           => 'Catalyst 9200',
            'sku'            => 'C9200-24T',
            'price'          => 2499.99,
            'stock_quantity' => 10,
        ]);

        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        Sanctum::actingAs($user);

        $response = $this->getJson(self::CART)->assertOk();

        $response->assertJsonPath('data.items.0.quantity', 2)
                 ->assertJsonPath('data.items.0.unit_price', '2499.99')
                 ->assertJsonPath('data.items.0.subtotal', '4999.98')
                 ->assertJsonPath('data.items.0.product.sku', 'C9200-24T')
                 ->assertJsonPath('data.subtotal', '4999.98');
    }

    public function test_show_creates_cart_on_first_access(): void
    {
        [$user, $business] = $this->customer();
        Sanctum::actingAs($user);

        $this->getJson(self::CART)->assertCreated();

        $this->assertSame(1, Cart::where('business_id', $business->id)->count());
    }

    public function test_show_reuses_existing_cart(): void
    {
        [$user, $business] = $this->customer();
        $existing = Cart::factory()->for($business)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(self::CART)->assertOk();

        $response->assertJsonPath('data.id', $existing->id);
        $this->assertSame(1, Cart::where('business_id', $business->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Add item
    |--------------------------------------------------------------------------
    */

    public function test_adding_a_product_creates_a_cart_item(): void
    {
        [$user, $business] = $this->customer();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        Sanctum::actingAs($user);

        $response = $this->postJson(self::ITEMS, [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.items.0.product_id', $product->id)
                 ->assertJsonPath('data.items.0.quantity', 3)
                 ->assertJsonPath('message', 'Item added to cart');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);
    }

    public function test_adding_same_product_twice_increases_quantity(): void
    {
        [$user, $business] = $this->customer();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        Sanctum::actingAs($user);

        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => 3])
            ->assertCreated();

        $this->assertSame(1, CartItem::where('product_id', $product->id)->count());
        $this->assertSame(5, CartItem::where('product_id', $product->id)->first()->quantity);
    }

    public function test_adding_beyond_stock_is_rejected_for_resulting_quantity(): void
    {
        [$user, $business] = $this->customer();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        Sanctum::actingAs($user);

        // Fill to 4.
        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => 4])
            ->assertCreated();

        // Attempt +2 → resulting 6 > 5.
        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(4, CartItem::where('product_id', $product->id)->first()->quantity);
    }

    public function test_adding_an_inactive_product_is_rejected(): void
    {
        [$user] = $this->customer();
        $product = Product::factory()->inactive()->create();
        Sanctum::actingAs($user);

        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_adding_a_nonexistent_product_is_rejected(): void
    {
        [$user] = $this->customer();
        Sanctum::actingAs($user);

        $this->postJson(self::ITEMS, ['product_id' => 99999, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_adding_with_invalid_quantity_is_rejected(): void
    {
        [$user] = $this->customer();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->postJson(self::ITEMS, ['product_id' => $product->id, 'quantity' => -3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_client_supplied_business_id_is_prohibited(): void
    {
        [$user] = $this->customer();
        $otherBusiness = Business::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(self::ITEMS, [
            'product_id'  => $product->id,
            'quantity'    => 1,
            'business_id' => $otherBusiness->id,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['business_id']);

        $this->assertDatabaseCount('cart_items', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Update item
    |--------------------------------------------------------------------------
    */

    public function test_updating_replaces_quantity(): void
    {
        [$user, $business] = $this->customer();
        $cart = Cart::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        Sanctum::actingAs($user);

        $this->patchJson(self::ITEMS . "/{$item->id}", ['quantity' => 7])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 7);

        $this->assertSame(7, $item->fresh()->quantity);
    }

    public function test_updating_beyond_stock_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $cart = Cart::factory()->for($business)->create();
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        Sanctum::actingAs($user);

        $this->patchJson(self::ITEMS . "/{$item->id}", ['quantity' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_cannot_update_another_business_cart_item(): void
    {
        [$user, $business] = $this->customer();
        $other = Business::factory()->create();
        $otherCart = Cart::factory()->for($other)->create();
        $otherItem = CartItem::factory()->for($otherCart)->create(['quantity' => 1]);

        Sanctum::actingAs($user);

        $this->patchJson(self::ITEMS . "/{$otherItem->id}", ['quantity' => 99])
            ->assertNotFound();

        $this->assertSame(1, $otherItem->fresh()->quantity);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete item
    |--------------------------------------------------------------------------
    */

    public function test_removing_an_item(): void
    {
        [$user, $business] = $this->customer();
        $cart = Cart::factory()->for($business)->create();
        $item = CartItem::factory()->for($cart)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(self::ITEMS . "/{$item->id}")
            ->assertOk()
            ->assertJson(['message' => 'Item removed from cart']);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_cannot_delete_another_business_cart_item(): void
    {
        [$user] = $this->customer();
        $other = Business::factory()->create();
        $otherCart = Cart::factory()->for($other)->create();
        $otherItem = CartItem::factory()->for($otherCart)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(self::ITEMS . "/{$otherItem->id}")->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Clear cart
    |--------------------------------------------------------------------------
    */

    public function test_clearing_all_items_preserves_the_cart(): void
    {
        [$user, $business] = $this->customer();
        $cart = Cart::factory()->for($business)->create();
        CartItem::factory()->count(3)->for($cart)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(self::ITEMS)
            ->assertOk()
            ->assertJson(['message' => 'Cart cleared']);

        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
        $this->assertSame(0, CartItem::where('cart_id', $cart->id)->count());
    }

    public function test_clearing_does_not_affect_other_business_carts(): void
    {
        [$user, $business] = $this->customer();
        $mine  = Cart::factory()->for($business)->create();
        $other = Business::factory()->create();
        $otherCart = Cart::factory()->for($other)->create();

        CartItem::factory()->count(2)->for($mine)->create();
        CartItem::factory()->count(3)->for($otherCart)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(self::ITEMS)->assertOk();

        $this->assertSame(0, CartItem::where('cart_id', $mine->id)->count());
        $this->assertSame(3, CartItem::where('cart_id', $otherCart->id)->count());
    }

    public function test_clearing_when_no_cart_exists_succeeds(): void
    {
        [$user] = $this->customer();
        Sanctum::actingAs($user);

        $this->deleteJson(self::ITEMS)
            ->assertOk()
            ->assertJson(['message' => 'Cart cleared']);
    }

    /*
    |--------------------------------------------------------------------------
    | Cross-business isolation
    |--------------------------------------------------------------------------
    */

    public function test_listing_cart_is_scoped_to_the_authenticated_business(): void
    {
        [$user, $business] = $this->customer();
        $other = Business::factory()->create();

        $myCart = Cart::factory()->for($business)->create();
        $otherCart = Cart::factory()->for($other)->create();

        $myProduct = Product::factory()->create(['stock_quantity' => 5]);
        $otherProduct = Product::factory()->create(['stock_quantity' => 5]);

        CartItem::factory()->for($myCart)->for($myProduct)->create(['quantity' => 1]);
        CartItem::factory()->count(3)->for($otherCart)->create();

        Sanctum::actingAs($user);

        $this->getJson(self::CART)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $myProduct->id);
    }
}