<?php

namespace Tests\Feature\Client\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORIES_ENDPOINT = '/api/v1/home/categories';
    private const PRODUCTS_ENDPOINT   = '/api/v1/home/products';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Home categories
    |--------------------------------------------------------------------------
    */

    public function test_categories_endpoint_is_public(): void
    {
        $this->getJson(self::CATEGORIES_ENDPOINT)->assertOk();
    }

    public function test_categories_returns_expected_structure(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_categories_returns_empty_when_none_exist(): void
    {
        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_categories_returns_at_most_five(): void
    {
        Category::factory()->count(10)->create();

        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_categories_returns_all_when_fewer_than_five_exist(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_categories_are_ordered_by_created_at_ascending(): void
    {
        $oldest = Category::factory()->create(['name' => 'Oldest']);
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        $middle = Category::factory()->create(['name' => 'Middle']);
        $middle->forceFill(['created_at' => now()->subDays(5)])->save();

        $newest = Category::factory()->create(['name' => 'Newest']);
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $oldest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $newest->id);
    }

    public function test_categories_limit_applies_to_the_oldest_five(): void
    {
        // Six categories with strictly increasing created_at values.
        for ($i = 0; $i < 6; $i++) {
            $category = Category::factory()->create(['name' => "Category {$i}"]);
            $category->forceFill(['created_at' => now()->subDays(100 - $i)])->save();
        }

        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.name', 'Category 0')
            ->assertJsonPath('data.4.name', 'Category 4');
    }

    public function test_categories_use_id_as_tie_breaker_when_created_at_matches(): void
    {
        $frozen = now()->subDay();
        Carbon::setTestNow($frozen);

        $first  = Category::factory()->create(['name' => 'First']);
        $second = Category::factory()->create(['name' => 'Second']);
        $third  = Category::factory()->create(['name' => 'Third']);

        Carbon::setTestNow();

        // Sanity: all three share the same created_at.
        $this->assertSame(3, Category::where('created_at', $frozen)->count());

        $response = $this->getJson(self::CATEGORIES_ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $third->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Home products
    |--------------------------------------------------------------------------
    */

    public function test_products_endpoint_is_public(): void
    {
        $this->getJson(self::PRODUCTS_ENDPOINT)->assertOk();
    }

    public function test_products_returns_expected_structure(): void
    {
        Product::factory()->create([
            'is_active'   => true,
            'is_featured' => true,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'sku', 'price', 'stock_quantity',
                        'is_active', 'is_featured',
                        'category', 'brand', 'images',
                    ],
                ],
            ]);
    }

    public function test_products_returns_empty_when_no_featured_products_exist(): void
    {
        Product::factory()->count(3)->create([
            'is_active'   => true,
            'is_featured' => false,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_products_only_includes_active_and_featured(): void
    {
        // Included.
        $included = Product::factory()->create([
            'is_active'   => true,
            'is_featured' => true,
        ]);

        // Excluded — featured but inactive.
        Product::factory()->create([
            'is_active'   => false,
            'is_featured' => true,
        ]);

        // Excluded — active but not featured.
        Product::factory()->create([
            'is_active'   => true,
            'is_featured' => false,
        ]);

        // Excluded — neither.
        Product::factory()->create([
            'is_active'   => false,
            'is_featured' => false,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $included->id);
    }

    public function test_products_returns_at_most_eight(): void
    {
        Product::factory()->count(12)->create([
            'is_active'   => true,
            'is_featured' => true,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()->assertJsonCount(8, 'data');
    }

    public function test_products_returns_all_when_fewer_than_eight_exist(): void
    {
        Product::factory()->count(3)->create([
            'is_active'   => true,
            'is_featured' => true,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_products_are_ordered_by_created_at_descending(): void
    {
        $oldest = Product::factory()->create([
            'is_active' => true, 'is_featured' => true, 'name' => 'Oldest',
        ]);
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        $middle = Product::factory()->create([
            'is_active' => true, 'is_featured' => true, 'name' => 'Middle',
        ]);
        $middle->forceFill(['created_at' => now()->subDays(5)])->save();

        $newest = Product::factory()->create([
            'is_active' => true, 'is_featured' => true, 'name' => 'Newest',
        ]);
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id);
    }

    public function test_products_limit_applies_to_the_newest_eight(): void
    {
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $product = Product::factory()->create([
                'is_active'   => true,
                'is_featured' => true,
                'name'        => "Product {$i}",
            ]);
            $product->forceFill(['created_at' => now()->subDays(100 - $i)])->save();
            $ids[$i] = $product->id;
        }

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()->assertJsonCount(8, 'data');

        // The eight newest are indexes 9 down to 2.
        $this->assertSame($ids[9], $response->json('data.0.id'));
        $this->assertSame($ids[2], $response->json('data.7.id'));
    }

    public function test_products_use_id_as_tie_breaker_when_created_at_matches(): void
    {
        $frozen = now()->subDay();
        Carbon::setTestNow($frozen);

        $first  = Product::factory()->create(['is_active' => true, 'is_featured' => true]);
        $second = Product::factory()->create(['is_active' => true, 'is_featured' => true]);
        $third  = Product::factory()->create(['is_active' => true, 'is_featured' => true]);

        Carbon::setTestNow();

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        // orderByDesc('id') → highest id first.
        $response->assertOk()
            ->assertJsonPath('data.0.id', $third->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $first->id);
    }

    public function test_products_include_category_brand_and_images_relations(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        $brand    = Brand::factory()->create(['name' => 'Acme']);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id'    => $brand->id,
            'is_active'   => true,
            'is_featured' => true,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.category.name', 'Electronics')
            ->assertJsonPath('data.0.brand.id', $brand->id)
            ->assertJsonPath('data.0.brand.name', 'Acme')
            ->assertJsonCount(1, 'data.0.images');
    }

    public function test_products_returns_empty_images_array_when_product_has_no_images(): void
    {
        Product::factory()->create([
            'is_active'   => true,
            'is_featured' => true,
        ]);

        $response = $this->getJson(self::PRODUCTS_ENDPOINT);

        $response->assertOk()->assertJsonCount(0, 'data.0.images');
    }
}