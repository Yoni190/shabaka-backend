<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id'     => Order::factory(),
            'product_id'   => Product::factory(),
            'product_name' => 'Sample Product',
            'sku'          => 'SKU-'.strtoupper(Str::random(6)),
            'quantity'     => 1,
            'unit_price'   => '10.00',
            'subtotal'     => '10.00',
        ];
    }
}