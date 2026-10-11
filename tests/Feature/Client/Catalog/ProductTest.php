<?php

namespace Tests\Feature\Client\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/products';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Index — access & shape
    |--------------------------------------------------------------------------
    */

    public function test_index_is_public(): void
    {
        $this->getJson(self::ENDPOINT)->assertOk();
    }

    public function test_index_returns_paginated_products(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'sku', 'price', 'stock_quantity',
                        'is_active', 'is_featured',
                        'category', 'brand', 'images',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Index — active-only guarantee
    |--------------------------------------------------------------------------
    */

    public function test_index_only_returns_active_products(): void
    {
        $active = Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_index_ignores_an_is_active_query_parameter(): void
    {
        // The storefront service intentionally drops is_active — even if a
        // client sends it, inactive products must never leak.
        Product::factory()->count(2)->create(['is_active' => true]);
        Product::factory()->count(3)->create(['is_active' => false]);

        $response = $this->getJson(self::ENDPOINT . '?is_active=0');

        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertTrue($row['is_active']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Index — pagination
    |--------------------------------------------------------------------------
    */

    public function test_index_respects_per_page(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson(self::ENDPOINT . '?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_caps_per_page_at_100(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson(self::ENDPOINT . '?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_defaults_per_page_when_missing(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_defaults_per_page_when_invalid(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson(self::ENDPOINT . '?per_page=abc');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
    }

    /*
    |--------------------------------------------------------------------------
    | Index — search & filters
    |--------------------------------------------------------------------------
    */

    public function test_index_searches_by_name_and_sku(): void
    {
        Product::factory()->create(['name' => 'Blue Widget', 'sku' => 'SKU-001', 'is_active' => true]);
        Product::factory()->create(['name' => 'Red Gadget',  'sku' => 'SKU-002', 'is_active' => true]);
        Product::factory()->create(['name' => 'Blue Gadget', 'sku' => 'ABC-999', 'is_active' => true]);

        $this->getJson(self::ENDPOINT . '?search=Blue')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson(self::ENDPOINT . '?search=SKU-00')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_search_does_not_return_inactive_products(): void
    {
        Product::factory()->create(['name' => 'Blue Widget', 'is_active' => true]);
        Product::factory()->create(['name' => 'Blue Widget', 'is_active' => false]);

        $this->getJson(self::ENDPOINT . '?search=Blue')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_category(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $categoryA->id, 'is_active' => true]);
        Product::factory()->create(['category_id' => $categoryB->id, 'is_active' => true]);

        $response = $this->getJson(self::ENDPOINT . "?category_id={$categoryA->id}");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_brand(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();

        Product::factory()->create(['brand_id' => $brandA->id, 'is_active' => true]);
        Product::factory()->count(2)->create(['brand_id' => $brandB->id, 'is_active' => true]);

        $response = $this->getJson(self::ENDPOINT . "?brand_id={$brandA->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_is_featured_true(): void
    {
        Product::factory()->count(2)->create(['is_active' => true, 'is_featured' => true]);
        Product::factory()->create(['is_active' => true, 'is_featured' => false]);

        $response = $this->getJson(self::ENDPOINT . '?is_featured=1');

        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertTrue($row['is_featured']);
        }
    }

    public function test_index_filters_by_is_featured_false(): void
    {
        // The "0" case is the one the `hasFilter()` guard exists for — it must
        // be treated as a real filter, not as an absent parameter.
        Product::factory()->create(['is_active' => true, 'is_featured' => true]);
        Product::factory()->count(2)->create(['is_active' => true, 'is_featured' => false]);

        $response = $this->getJson(self::ENDPOINT . '?is_featured=0');

        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertFalse($row['is_featured']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Index — sorting
    |--------------------------------------------------------------------------
    */

    public function test_index_defaults_to_created_at_descending(): void
    {
        $oldest = Product::factory()->create(['is_active' => true, 'name' => 'Oldest']);
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        $middle = Product::factory()->create(['is_active' => true, 'name' => 'Middle']);
        $middle->forceFill(['created_at' => now()->subDays(5)])->save();

        $newest = Product::factory()->create(['is_active' => true, 'name' => 'Newest']);
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id);
    }

    public function test_index_sorts_by_name_ascending(): void
    {
        Product::factory()->create(['is_active' => true, 'name' => 'Charlie']);
        Product::factory()->create(['is_active' => true, 'name' => 'Alpha']);
        Product::factory()->create(['is_active' => true, 'name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT . '?sort=name&direction=asc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    public function test_index_sorts_by_price_descending(): void
    {
        Product::factory()->create(['is_active' => true, 'price' => 10]);
        Product::factory()->create(['is_active' => true, 'price' => 30]);
        Product::factory()->create(['is_active' => true, 'price' => 20]);

        $response = $this->getJson(self::ENDPOINT . '?sort=price&direction=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.price', '30.00')
            ->assertJsonPath('data.1.price', '20.00')
            ->assertJsonPath('data.2.price', '10.00');
    }

    public function test_index_ignores_non_sortable_columns(): void
    {
        Product::factory()->create(['is_active' => true, 'name' => 'Zed']);
        Product::factory()->create(['is_active' => true, 'name' => 'Abe']);

        // Falls back to created_at desc — no error, no injection.
        $response = $this->getJson(self::ENDPOINT . '?sort=password');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Index — relations & images
    |--------------------------------------------------------------------------
    */

    public function test_index_includes_category_brand_and_ordered_images(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        $brand    = Brand::factory()->create(['name' => 'Acme']);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id'    => $brand->id,
            'is_active'   => true,
        ]);

        // Insert out of order to prove the service orders by sort_order, id.
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.brand.id', $brand->id)
            ->assertJsonCount(3, 'data.0.images')
            ->assertJsonPath('data.0.images.0.sort_order', 0)
            ->assertJsonPath('data.0.images.1.sort_order', 1)
            ->assertJsonPath('data.0.images.2.sort_order', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_show_is_public(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->getJson(self::ENDPOINT . "/{$product->id}")->assertOk();
    }

    public function test_show_returns_an_active_product_with_relations(): void
    {
        $category = Category::factory()->create();
        $brand    = Brand::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id'    => $brand->id,
            'is_active'   => true,
        ]);

        $response = $this->getJson(self::ENDPOINT . "/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonStructure(['data' => ['images']]);
    }

    public function test_show_orders_images_by_sort_order(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 5]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 3]);

        $response = $this->getJson(self::ENDPOINT . "/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.images.0.sort_order', 1)
            ->assertJsonPath('data.images.1.sort_order', 3)
            ->assertJsonPath('data.images.2.sort_order', 5);
    }

    public function test_show_returns_empty_images_array_when_product_has_no_images(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->getJson(self::ENDPOINT . "/{$product->id}");

        $response->assertOk()->assertJsonCount(0, 'data.images');
    }

    public function test_show_returns_404_for_inactive_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->getJson(self::ENDPOINT . "/{$product->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $this->getJson(self::ENDPOINT . '/99999')->assertNotFound();
    }
}