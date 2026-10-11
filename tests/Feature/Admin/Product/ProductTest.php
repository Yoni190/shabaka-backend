<?php

namespace Tests\Feature\Admin\Product;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/products';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        // The ProductResource builds image URLs from this config key.
        config()->set('filesystems.disks.s3.url', 'https://cdn.example.com');

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_index_returns_paginated_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'sku', 'price', 'stock_quantity',
                            'is_active', 'is_featured', 'category', 'brand'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_respects_per_page(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_caps_per_page_at_100(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_falls_back_to_default_per_page_when_invalid(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=abc');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_searches_by_name_and_sku(): void
    {
        Product::factory()->create(['name' => 'Blue Widget', 'sku' => 'SKU-001']);
        Product::factory()->create(['name' => 'Red Gadget',  'sku' => 'SKU-002']);
        Product::factory()->create(['name' => 'Blue Gadget', 'sku' => 'ABC-999']);

        $byName = $this->getJson(self::ENDPOINT . '?search=Blue');
        $byName->assertOk()->assertJsonCount(2, 'data');

        $bySku = $this->getJson(self::ENDPOINT . '?search=SKU-00');
        $bySku->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_category(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $categoryA->id]);
        Product::factory()->create(['category_id' => $categoryB->id]);

        $response = $this->getJson(self::ENDPOINT . "?category_id={$categoryA->id}");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_brand(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();

        Product::factory()->create(['brand_id' => $brandA->id]);
        Product::factory()->count(2)->create(['brand_id' => $brandB->id]);

        $response = $this->getJson(self::ENDPOINT . "?brand_id={$brandA->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_is_active(): void
    {
        Product::factory()->count(2)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $active = $this->getJson(self::ENDPOINT . '?is_active=1');
        $active->assertOk()->assertJsonCount(2, 'data');

        // "0" must be treated as a real filter, not as an absent value.
        $inactive = $this->getJson(self::ENDPOINT . '?is_active=0');
        $inactive->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_sorts_by_name_ascending_by_default(): void
    {
        Product::factory()->create(['name' => 'Charlie']);
        Product::factory()->create(['name' => 'Alpha']);
        Product::factory()->create(['name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    public function test_index_sorts_by_price_descending(): void
    {
        Product::factory()->create(['name' => 'A', 'price' => 10]);
        Product::factory()->create(['name' => 'B', 'price' => 30]);
        Product::factory()->create(['name' => 'C', 'price' => 20]);

        $response = $this->getJson(self::ENDPOINT . '?sort=price&direction=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.price', '30.00')
            ->assertJsonPath('data.1.price', '20.00')
            ->assertJsonPath('data.2.price', '10.00');
    }

    public function test_index_ignores_non_sortable_columns(): void
    {
        Product::factory()->create(['name' => 'Zed']);
        Product::factory()->create(['name' => 'Abe']);

        $response = $this->getJson(self::ENDPOINT . '?sort=password');

        // Falls back to name asc — no error, no injection.
        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Abe');
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_store_creates_a_product(): void
    {
        $category = Category::factory()->create();
        $brand    = Brand::factory()->create();

        $response = $this->postJson(self::ENDPOINT, [
            'category_id'    => $category->id,
            'brand_id'       => $brand->id,
            'name'           => 'Blue Widget',
            'sku'            => 'SKU-001',
            'description'    => 'A very blue widget.',
            'price'          => 19.99,
            'stock_quantity' => 10,
            'is_active'      => true,
            'is_featured'    => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Blue Widget')
            ->assertJsonPath('data.sku', 'SKU-001')
            ->assertJsonPath('message', 'Product created successfully');

        $this->assertDatabaseHas('products', [
            'sku'         => 'SKU-001',
            'category_id' => $category->id,
            'brand_id'    => $brand->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson(self::ENDPOINT, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id', 'brand_id', 'name', 'sku',
                'price', 'stock_quantity', 'is_active', 'is_featured',
            ]);
    }

    public function test_store_rejects_duplicate_sku(): void
    {
        Product::factory()->create(['sku' => 'SKU-001']);

        $response = $this->postJson(self::ENDPOINT, [
            'category_id'    => Category::factory()->create()->id,
            'brand_id'       => Brand::factory()->create()->id,
            'name'           => 'Another Widget',
            'sku'            => 'SKU-001',
            'price'          => 5,
            'stock_quantity' => 1,
            'is_active'      => true,
            'is_featured'    => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_store_rejects_unknown_category_or_brand(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'category_id'    => 99999,
            'brand_id'       => 99999,
            'name'           => 'Widget',
            'sku'            => 'SKU-NEW',
            'price'          => 5,
            'stock_quantity' => 1,
            'is_active'      => true,
            'is_featured'    => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'brand_id']);
    }

    public function test_store_uploads_images_and_marks_first_as_primary(): void
    {
        $category = Category::factory()->create();
        $brand    = Brand::factory()->create();

        $response = $this->postJson(self::ENDPOINT, [
            'category_id'    => $category->id,
            'brand_id'       => $brand->id,
            'name'           => 'Widget',
            'sku'            => 'SKU-IMG',
            'price'          => 10,
            'stock_quantity' => 5,
            'is_active'      => true,
            'is_featured'    => false,
            'images'         => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.png'),
            ],
        ]);

        $response->assertCreated();

        $product = Product::where('sku', 'SKU-IMG')->firstOrFail();
        $this->assertCount(2, $product->images);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        foreach ($product->images as $image) {
            Storage::disk('s3')->assertExists($image->path);
        }
    }

    public function test_store_rejects_invalid_image_types(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'category_id'    => Category::factory()->create()->id,
            'brand_id'       => Brand::factory()->create()->id,
            'name'           => 'Widget',
            'sku'            => 'SKU-BAD-IMG',
            'price'          => 10,
            'stock_quantity' => 5,
            'is_active'      => true,
            'is_featured'    => false,
            'images'         => [
                UploadedFile::fake()->create('document.pdf', 10),
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0']);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_show_returns_a_product_with_relations(): void
    {
        $category = Category::factory()->create();
        $brand    = Brand::factory()->create();
        $product  = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id'    => $brand->id,
        ]);

        $response = $this->getJson(self::ENDPOINT . "/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonStructure(['data' => ['images']]);
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $response = $this->getJson(self::ENDPOINT . '/99999');

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_update_modifies_a_product(): void
    {
        $product = Product::factory()->create([
            'name'           => 'Old Name',
            'sku'            => 'SKU-OLD',
            'price'          => 10,
            'stock_quantity' => 1,
        ]);

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", [
            'category_id'    => $product->category_id,
            'brand_id'       => $product->brand_id,
            'name'           => 'New Name',
            'sku'            => 'SKU-NEW',
            'price'          => 25.5,
            'stock_quantity' => 20,
            'is_active'      => true,
            'is_featured'    => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.sku', 'SKU-NEW')
            ->assertJsonPath('message', 'Product updated successfully');

        $this->assertDatabaseHas('products', [
            'id'             => $product->id,
            'name'           => 'New Name',
            'sku'            => 'SKU-NEW',
            'stock_quantity' => 20,
        ]);
    }

    public function test_update_allows_keeping_the_same_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-KEEP']);

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", [
            'category_id'    => $product->category_id,
            'brand_id'       => $product->brand_id,
            'name'           => 'Renamed',
            'sku'            => 'SKU-KEEP',
            'price'          => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'is_active'      => $product->is_active,
            'is_featured'    => $product->is_featured,
        ]);

        $response->assertOk();
    }

    public function test_update_rejects_sku_taken_by_another_product(): void
    {
        Product::factory()->create(['sku' => 'SKU-TAKEN']);
        $product = Product::factory()->create(['sku' => 'SKU-MINE']);

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", [
            'category_id'    => $product->category_id,
            'brand_id'       => $product->brand_id,
            'name'           => $product->name,
            'sku'            => 'SKU-TAKEN',
            'price'          => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'is_active'      => $product->is_active,
            'is_featured'    => $product->is_featured,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_update_deletes_images_and_removes_them_from_s3(): void
    {
        $product = Product::factory()->create();
        $image   = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => 'products/' . $product->id . '/old.jpg',
        ]);

        Storage::disk('s3')->put($image->path, 'contents');

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", [
            'category_id'      => $product->category_id,
            'brand_id'         => $product->brand_id,
            'name'             => $product->name,
            'sku'              => $product->sku,
            'price'            => $product->price,
            'stock_quantity'   => $product->stock_quantity,
            'is_active'        => $product->is_active,
            'is_featured'      => $product->is_featured,
            'delete_image_ids' => [$image->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('s3')->assertMissing($image->path);
    }

    public function test_update_rejects_delete_image_ids_not_belonging_to_product(): void
    {
        $product    = Product::factory()->create();
        $otherImage = ProductImage::factory()->create();

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", [
            'category_id'      => $product->category_id,
            'brand_id'         => $product->brand_id,
            'name'             => $product->name,
            'sku'              => $product->sku,
            'price'            => $product->price,
            'stock_quantity'   => $product->stock_quantity,
            'is_active'        => $product->is_active,
            'is_featured'      => $product->is_featured,
            'delete_image_ids' => [$otherImage->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delete_image_ids.0']);

        $this->assertDatabaseHas('product_images', ['id' => $otherImage->id]);
    }

    public function test_update_appends_new_images_after_existing_ones(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'sort_order' => 5,
            'is_primary' => true,
        ]);

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", [
            'category_id'    => $product->category_id,
            'brand_id'       => $product->brand_id,
            'name'           => $product->name,
            'sku'            => $product->sku,
            'price'          => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'is_active'      => $product->is_active,
            'is_featured'    => $product->is_featured,
            'images'         => [
                UploadedFile::fake()->image('new.jpg'),
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'sort_order' => 6,
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson(self::ENDPOINT . "/{$product->id}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id', 'brand_id', 'name', 'sku',
                'price', 'stock_quantity', 'is_active', 'is_featured',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_destroy_deletes_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson(self::ENDPOINT . "/{$product->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Product deleted successfully']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_deletes_associated_images_from_s3(): void
    {
        $product = Product::factory()->create();
        $image   = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => 'products/' . $product->id . '/gone.jpg',
        ]);

        Storage::disk('s3')->put($image->path, 'contents');

        $this->deleteJson(self::ENDPOINT . "/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('s3')->assertMissing($image->path);
    }

    public function test_destroy_returns_404_for_missing_product(): void
    {
        $response = $this->deleteJson(self::ENDPOINT . '/99999');

        $response->assertNotFound();
    }
}