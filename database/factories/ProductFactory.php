<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id'    => Category::factory(),
            'brand_id'       => Brand::factory(),
            'name'           => 'Product '.$this->faker->unique()->numberBetween(1, 999_999),
            'sku'            => 'SKU-'.$this->faker->unique()->numberBetween(1, 999_999),
            'description'    => null,
            'price'          => $this->faker->randomFloat(2, 1, 5000),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'is_active'      => true,
            'is_featured'    => false,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}