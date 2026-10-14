<?php

namespace App\Services\Client\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Create an order from the authenticated user's business cart.
     *
     * Stock is decremented at order placement, inside the same transaction
     * that creates the order. Cancellation is responsible for restoring it
     * (not implemented in this task).
     *
     * @param  array{shipping_address_id: int|string, billing_address_id?: int|string|null, notes?: string|null}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function createFromCart(User $user, array $data): Order
    {
        $business = $this->businessFor($user);

        return DB::transaction(function () use ($user, $business, $data) {
            // Lock the cart row first. Concurrent order attempts for the
            // same business serialize here; the loser sees an empty cart.
            $cart = $business->cart()->lockForUpdate()->first();

            if (! $cart || $cart->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty.'],
                ]);
            }

            $shipping = $this->resolveAddress(
                $business,
                isset($data['shipping_address_id']) ? (int) $data['shipping_address_id'] : null,
                'shipping_address_id',
                required: true,
            );

            $billing = $this->resolveAddress(
                $business,
                isset($data['billing_address_id']) ? (int) $data['billing_address_id'] : null,
                'billing_address_id',
                required: false,
            );

            $cartItems = $cart->items()->get();
            $productIds = $cartItems->pluck('product_id')->unique()->values()->all();

            // Lock product rows: concurrent orders cannot both pass the
            // stock check and both decrement.
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalCents = 0;
            $lineItems = [];

            foreach ($cartItems as $cartItem) {
                /** @var Product|null $product */
                $product = $products->get($cartItem->product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'cart' => ['One of the products in your cart no longer exists.'],
                    ]);
                }

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => ["'{$product->name}' is no longer available for purchase."],
                    ]);
                }

                if ($product->stock_quantity < $cartItem->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ["Insufficient stock for '{$product->name}'. Only {$product->stock_quantity} available."],
                    ]);
                }

                $unitCents = (int) round(((float) $product->price) * 100);
                $lineCents = $unitCents * $cartItem->quantity;
                $subtotalCents += $lineCents;

                $lineItems[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'sku'          => $product->sku,
                    'quantity'     => $cartItem->quantity,
                    'unit_price'   => self::centsToString($unitCents),
                    'subtotal'     => self::centsToString($lineCents),
                ];
            }

            $subtotal = self::centsToString($subtotalCents);

            // Tax, shipping, and discount remain zero until the business
            // rules for them are defined. Total = subtotal for now.
            $order = Order::create([
                'order_number'              => $this->generateOrderNumber(),
                'business_id'               => $business->id,
                'user_id'                   => $user->id,
                'shipping_address_id'       => $shipping->id,
                'billing_address_id'        => $billing?->id,
                'shipping_address_snapshot' => self::addressSnapshot($shipping),
                'billing_address_snapshot'  => $billing ? self::addressSnapshot($billing) : null,
                'status'                    => OrderStatus::PENDING,
                'payment_status'            => PaymentStatus::UNPAID,
                'subtotal'                  => $subtotal,
                'tax_amount'                => '0.00',
                'shipping_amount'           => '0.00',
                'discount_amount'           => '0.00',
                'total_amount'              => $subtotal,
                'currency'                  => 'SAR',
                'notes'                     => $data['notes'] ?? null,
            ]);

            foreach ($lineItems as $line) {
                $order->items()->create($line);
            }

            foreach ($cartItems as $cartItem) {
                $products->get($cartItem->product_id)
                    ->decrement('stock_quantity', $cartItem->quantity);
            }

            // Clearing the cart inside the transaction means a failed order
            // leaves the cart intact. Repeat submissions cannot duplicate
            // the order: after commit, the cart is empty.
            $cart->items()->delete();

            return $order->load(['items', 'business', 'user']);
        });
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
     * Scoped lookup: an address belonging to another business is
     * indistinguishable from a missing one → validation error.
     *
     * @throws ValidationException
     */
    private function resolveAddress(
        Business $business,
        ?int $addressId,
        string $field,
        bool $required,
    ): ?Address {
        if ($addressId === null) {
            if ($required) {
                throw ValidationException::withMessages([
                    $field => ['A shipping address is required.'],
                ]);
            }

            return null;
        }

        $address = $business->addresses()->find($addressId);

        if (! $address) {
            throw ValidationException::withMessages([
                $field => ['The selected address does not belong to your business.'],
            ]);
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private static function addressSnapshot(Address $address): array
    {
        return [
            'label'           => $address->label,
            'recipient_name'  => $address->recipient_name,
            'company_name'    => $address->company_name,
            'phone'           => $address->phone,
            'address_line_1'  => $address->address_line_1,
            'address_line_2'  => $address->address_line_2,
            'city'            => $address->city,
            'state_or_region' => $address->state_or_region,
            'postal_code'     => $address->postal_code,
            'country_code'    => $address->country_code,
        ];
    }

    private static function centsToString(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Format: ORD-YYYYMMDD-XXXXXXXX (8 uppercase alphanumerics).
     * Uniqueness is guaranteed by the database constraint; the pre-check
     * only reduces retries on the astronomically rare collision.
     */
    private function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = "ORD-{$date}-".strtoupper(Str::random(8));

            if (! Order::where('order_number', $number)->exists()) {
                return $number;
            }
        }

        return 'ORD-'.strtoupper((string) Str::ulid());
    }
}