<?php

namespace App\Services\Client\Cart;

use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * Retrieve (or lazily create) the authenticated user's business cart.
     *
     * @throws AuthorizationException
     */
    public function view(User $user): Cart
    {
        $business = $this->businessFor($user);

        return $this->loadCart($this->ensureCart($business));
    }

    /**
     * Add a product to the cart, or increase the quantity if the product is
     * already present.
     *
     * @param  array{product_id: int|string, quantity: int|string}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function addItem(User $user, array $data): Cart
    {
        $business = $this->businessFor($user);
        $product  = Product::findOrFail($data['product_id']);
        $quantity = (int) $data['quantity'];

        $this->assertPurchasable($product);

        return DB::transaction(function () use ($business, $product, $quantity) {
            // Serialize all cart writes for this business by locking the
            // cart row. Prevents two concurrent adds of the same product
            // from both reading quantity=0 and both writing quantity=N.
            $cart = $this->ensureCart($business, lock: true);

            $item = $cart->items()->where('product_id', $product->id)->first();
            $resultingQuantity = ($item?->quantity ?? 0) + $quantity;

            // Validate the *resulting* quantity, not just the submitted one.
            $this->assertStockAvailable($product, $resultingQuantity);

            if ($item) {
                $item->update(['quantity' => $resultingQuantity]);
            } else {
                try {
                    $cart->items()->create([
                        'product_id' => $product->id,
                        'quantity'   => $resultingQuantity,
                    ]);
                } catch (QueryException $e) {
                    // Race: another request inserted the same (cart, product)
                    // row between our SELECT and INSERT. The unique constraint
                    // fires; retry the update path.
                    $cart->items()
                        ->where('product_id', $product->id)
                        ->update(['quantity' => $resultingQuantity]);
                }
            }

            return $this->loadCart($cart->fresh());
        });
    }

    /**
     * Replace an item's quantity. Does not increment.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateItem(User $user, int $itemId, int $quantity): Cart
    {
        $business = $this->businessFor($user);

        return DB::transaction(function () use ($business, $itemId, $quantity) {
            $cart = $this->ensureCart($business, lock: true);

            // Scoped lookup: an item belonging to another cart is
            // indistinguishable from a missing one → 404.
            $item = $cart->items()->whereKey($itemId)->firstOrFail();

            $this->assertStockAvailable($item->product, $quantity);

            $item->update(['quantity' => $quantity]);

            return $this->loadCart($cart->fresh());
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function removeItem(User $user, int $itemId): void
    {
        $business = $this->businessFor($user);
        $cart     = $this->ensureCart($business);

        $cart->items()->whereKey($itemId)->firstOrFail()->delete();
    }

    /**
     * Remove every item from the cart without deleting the cart itself.
     *
     * @throws AuthorizationException
     */
    public function clear(User $user): void
    {
        $business = $this->businessFor($user);

        $business->cart?->items()->delete();
    }

    /**
     * @throws AuthorizationException
     */
    private function businessFor(User $user): Business
    {
        $business = $user->business;

        if (! $business) {
            throw new AuthorizationException('Your account is not associated with a business.');
        }

        return $business;
    }

    /**
     * Return the business's cart, creating it if missing.
     *
     * Creation is safe under concurrent requests: the unique constraint on
     * `carts.business_id` rejects the loser, which then re-fetches.
     */
    private function ensureCart(Business $business, bool $lock = false): Cart
    {
        $query = $business->cart();

        if ($lock) {
            $query = $query->lockForUpdate();
        }

        $cart = $query->first();

        if ($cart) {
            return $cart;
        }

        try {
            return $business->cart()->create();
        } catch (QueryException $e) {
            // Another request created it first.
            return $business->cart()->firstOrFail();
        }
    }

    private function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.brand',
            'items.product.category',
            'items.product.images',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function assertPurchasable(Product $product): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is not available for purchase.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertStockAvailable(Product $product, int $quantity): void
    {
        if ($quantity > $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$product->stock_quantity} unit(s) of {$product->name} are available."],
            ]);
        }
    }
}