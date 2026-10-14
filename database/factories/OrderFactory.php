<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number'              => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
            'business_id'               => Business::factory(),
            'user_id'                   => null,
            'shipping_address_id'       => null,
            'billing_address_id'        => null,
            'shipping_address_snapshot' => null,
            'billing_address_snapshot'  => null,
            'status'                    => OrderStatus::PENDING,
            'payment_status'            => PaymentStatus::UNPAID,
            'subtotal'                  => '0.00',
            'tax_amount'                => '0.00',
            'shipping_amount'           => '0.00',
            'discount_amount'           => '0.00',
            'total_amount'              => '0.00',
            'currency'                  => 'USD',
            'notes'                     => null,
        ];
    }
}