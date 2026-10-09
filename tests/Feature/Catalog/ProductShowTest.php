<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_product_without_authentication(): void
    {
        $product = Product::factory()->create();

        // No Authorization header — endpoint must be public.
        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_it_returns_expected_product_details(): void
    {
        $product = Product::factory()->create([
            'name'  => 'Managed Network Switch',
            'sku'   => 'SW-001',
            'price' => 499.00,
        ]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'sku', 'description', 'price',
                    'stock_quantity', 'is_active', 'is_featured',
                    'category' => ['id', 'name'],
                    'brand'    => ['id', 'name'],
                    'images',
                    'created_at', 'updated_at',
                ],
            ])
            ->assertJsonPath('data.name', 'Managed Network Switch')
            ->assertJsonPath('data.sku', 'SW-001')
            ->assertJsonPath('data.price', '499.00');
    }

    public function test_it_includes_category_and_brand(): void
    {
        $category = Category::factory()->create(['name' => 'Switches']);
        $brand    = Brand::factory()->create(['name' => 'Cisco']);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id'    => $brand->id,
        ]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'Switches')
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonPath('data.brand.name', 'Cisco');
    }

    public function test_it_returns_all_images_in_display_order(): void
    {
        $product = Product::factory()->create();

        // Insert deliberately out of order to prove the ordering works.
        ProductImage::factory()->for($product)->create([
            'path' => 'products/1/third.jpg', 'sort_order' => 3,
        ]);
        ProductImage::factory()->for($product)->primary()->create([
            'path' => 'products/1/first.jpg', 'sort_order' => 1,
        ]);
        ProductImage::factory()->for($product)->create([
            'path' => 'products/1/second.jpg', 'sort_order' => 2,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}")->assertOk();

        $this->assertSame(
            ['products/1/first.jpg', 'products/1/second.jpg', 'products/1/third.jpg'],
            array_column($response->json('data.images'), 'path'),
        );
    }

    public function test_it_marks_primary_image_identifiable(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->primary()->create([
            'path' => 'products/1/cover.jpg', 'sort_order' => 1,
        ]);
        ProductImage::factory()->for($product)->create([
            'path' => 'products/1/detail.jpg', 'sort_order' => 2,
        ]);

        $images = $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->json('data.images');

        $primary = array_values(array_filter($images, fn ($i) => $i['is_primary'] === true));

        $this->assertCount(1, $primary);
        $this->assertSame('products/1/cover.jpg', $primary[0]['path']);
        $this->assertSame(1, $primary[0]['sort_order']);
    }

    public function test_it_returns_empty_images_array_when_no_images(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.images');
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $this->getJson('/api/v1/products/99999')->assertNotFound();
    }

    public function test_inactive_product_returns_404(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
    }

    public function test_inactive_product_is_not_leaked(): void
    {
        $active   = Product::factory()->create();
        $inactive = Product::factory()->inactive()->create();

        // Active product is reachable.
        $this->getJson("/api/v1/products/{$active->id}")->assertOk();

        $this->getJson("/api/v1/products/{$inactive->id}")->assertNotFound();
        $this->getJson('/api/v1/products/99999')->assertNotFound();
    }
}