<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/brands';

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
        Storage::fake(config('filesystems.uploads_disk', 's3'));
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_index_returns_paginated_brands(): void
    {
        Brand::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_respects_per_page(): void
    {
        Brand::factory()->count(5)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_caps_per_page_at_100(): void
    {
        Brand::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_defaults_per_page_when_missing(): void
    {
        Brand::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_defaults_per_page_when_invalid(): void
    {
        Brand::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=abc');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_searches_by_name(): void
    {
        Brand::factory()->create(['name' => 'Acme']);
        Brand::factory()->create(['name' => 'Acme Corporation']);
        Brand::factory()->create(['name' => 'Globex']);

        $response = $this->getJson(self::ENDPOINT . '?search=Acme');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_sorts_by_name_ascending_by_default(): void
    {
        Brand::factory()->create(['name' => 'Charlie']);
        Brand::factory()->create(['name' => 'Alpha']);
        Brand::factory()->create(['name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    public function test_index_sorts_by_name_descending(): void
    {
        Brand::factory()->create(['name' => 'Alpha']);
        Brand::factory()->create(['name' => 'Charlie']);
        Brand::factory()->create(['name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT . '?sort=name&direction=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Charlie')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Alpha');
    }

    public function test_index_ignores_non_sortable_columns(): void
    {
        Brand::factory()->create(['name' => 'Zed']);
        Brand::factory()->create(['name' => 'Abe']);

        $response = $this->getJson(self::ENDPOINT . '?sort=description');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Abe');
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_store_creates_a_brand(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Acme',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Acme')
            ->assertJsonPath('message', 'Brand created successfully');

        $this->assertDatabaseHas('brands', ['name' => 'Acme']);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson(self::ENDPOINT, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Brand::factory()->create(['name' => 'Acme']);

        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Acme',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_name_longer_than_255_characters(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

        public function test_store_creates_a_brand_with_an_image(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name'        => 'Acme',
            'brand_image' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Acme')
            ->assertJsonPath('message', 'Brand created successfully');

        $brand = Brand::where('name', 'Acme')->firstOrFail();

        $this->assertNotNull($brand->brand_image);
        $this->assertStringStartsWith("brands/{$brand->id}/", $brand->brand_image);
        Storage::disk($this->disk())->assertExists($brand->brand_image);

        $response->assertJsonPath('data.brand_image', $brand->brand_image);
        $this->assertNotNull($response->json('data.brand_image_url'));
        $this->assertStringContainsString(
            $brand->brand_image,
            $response->json('data.brand_image_url'),
        );
    }

    public function test_store_returns_null_image_fields_when_no_image_is_uploaded(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'No Image Brand',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.brand_image', null)
            ->assertJsonPath('data.brand_image_url', null);

        $this->assertDatabaseHas('brands', [
            'name'        => 'No Image Brand',
            'brand_image' => null,
        ]);
    }

    public function test_store_rejects_non_image_file(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name'        => 'Bad Upload',
            'brand_image' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_image']);

        $this->assertDatabaseMissing('brands', ['name' => 'Bad Upload']);
    }

    public function test_store_rejects_oversized_image(): void
    {
        // Configured max is 2048 KB; send 3000 KB.
        $response = $this->postJson(self::ENDPOINT, [
            'name'        => 'Huge Image',
            'brand_image' => UploadedFile::fake()->create('huge.jpg', 3000),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_image']);
    }

    public function test_store_delegates_image_storage_to_file_storage_service(): void
    {
        $this->mock(FileStorageService::class, function ($mock) {
            $mock->shouldReceive('store')
                ->once()
                ->with(
                    Mockery::type(UploadedFile::class),
                    Mockery::on(fn ($dir) => is_string($dir) && preg_match('#^brands/\d+$#', $dir) === 1),
                )
                ->andReturn('brands/999/mocked.jpg');
            $mock->shouldReceive('delete')->never();
        });

        $this->postJson(self::ENDPOINT, [
            'name'        => 'Mocked Brand',
            'brand_image' => UploadedFile::fake()->image('logo.jpg'),
        ])->assertCreated();

        $this->assertDatabaseHas('brands', [
            'name'        => 'Mocked Brand',
            'brand_image' => 'brands/999/mocked.jpg',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_show_returns_a_brand_with_product_count(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->count(3)->create(['brand_id' => $brand->id]);

        $response = $this->getJson(self::ENDPOINT . "/{$brand->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $brand->id)
            ->assertJsonPath('data.products_count', 3);
    }

    public function test_show_returns_zero_product_count_when_brand_is_empty(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson(self::ENDPOINT . "/{$brand->id}");

        $response->assertOk()
            ->assertJsonPath('data.products_count', 0);
    }

    public function test_show_returns_404_for_missing_brand(): void
    {
        $response = $this->getJson(self::ENDPOINT . '/99999');

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_update_modifies_a_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('message', 'Brand updated successfully');

        $this->assertDatabaseHas('brands', [
            'id'   => $brand->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_allows_keeping_the_same_name(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name' => 'Acme',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Acme');
    }

    public function test_update_rejects_name_taken_by_another_brand(): void
    {
        Brand::factory()->create(['name' => 'Taken']);
        $brand = Brand::factory()->create(['name' => 'Mine']);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name' => 'Taken',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validates_required_name(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

        public function test_update_replaces_brand_image_and_deletes_old_file(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $oldPath = "brands/{$brand->id}/old.jpg";
        Storage::disk($this->disk())->put($oldPath, 'old content');
        $brand->update(['brand_image' => $oldPath]);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name'        => 'Acme',
            'brand_image' => UploadedFile::fake()->image('new.jpg'),
        ]);

        $response->assertOk();

        $brand->refresh();

        $this->assertNotNull($brand->brand_image);
        $this->assertNotSame($oldPath, $brand->brand_image);
        $this->assertStringStartsWith("brands/{$brand->id}/", $brand->brand_image);
        Storage::disk($this->disk())->assertExists($brand->brand_image);
        Storage::disk($this->disk())->assertMissing($oldPath);
    }

    public function test_update_without_image_preserves_existing_brand_image(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $existingPath = "brands/{$brand->id}/existing.jpg";
        Storage::disk($this->disk())->put($existingPath, 'content');
        $brand->update(['brand_image' => $existingPath]);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name' => 'Acme Renamed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Acme Renamed')
            ->assertJsonPath('data.brand_image', $existingPath);

        $brand->refresh();
        $this->assertSame($existingPath, $brand->brand_image);
        Storage::disk($this->disk())->assertExists($existingPath);
    }

    public function test_update_adds_image_to_brand_without_one(): void
    {
        $brand = Brand::factory()->create(['name' => 'No Image']);
        $this->assertNull($brand->brand_image);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name'        => 'No Image',
            'brand_image' => UploadedFile::fake()->image('first.jpg'),
        ]);

        $response->assertOk();

        $brand->refresh();
        $this->assertNotNull($brand->brand_image);
        $this->assertStringStartsWith("brands/{$brand->id}/", $brand->brand_image);
        Storage::disk($this->disk())->assertExists($brand->brand_image);
    }

    public function test_update_with_invalid_image_keeps_existing_brand_image(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $existingPath = "brands/{$brand->id}/existing.jpg";
        Storage::disk($this->disk())->put($existingPath, 'content');
        $brand->update(['brand_image' => $existingPath]);

        $response = $this->putJson(self::ENDPOINT . "/{$brand->id}", [
            'name'        => 'Acme',
            'brand_image' => UploadedFile::fake()->create('bad.pdf', 100),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_image']);

        $brand->refresh();
        $this->assertSame($existingPath, $brand->brand_image);
        Storage::disk($this->disk())->assertExists($existingPath);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_destroy_deletes_an_empty_brand(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->deleteJson(self::ENDPOINT . "/{$brand->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Brand deleted successfully']);

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_destroy_returns_409_when_brand_has_products(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->create(['brand_id' => $brand->id]);

        $response = $this->deleteJson(self::ENDPOINT . "/{$brand->id}");

        $response->assertStatus(409);

        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    public function test_destroy_returns_404_for_missing_brand(): void
    {
        $response = $this->deleteJson(self::ENDPOINT . '/99999');

        $response->assertNotFound();
    }

        public function test_destroy_deletes_brand_image(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $path = "brands/{$brand->id}/image.jpg";
        Storage::disk($this->disk())->put($path, 'content');
        $brand->update(['brand_image' => $path]);

        $response = $this->deleteJson(self::ENDPOINT . "/{$brand->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Brand deleted successfully']);

        Storage::disk($this->disk())->assertMissing($path);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_destroy_preserves_image_when_brand_has_products(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $path = "brands/{$brand->id}/image.jpg";
        Storage::disk($this->disk())->put($path, 'content');
        $brand->update(['brand_image' => $path]);

        Product::factory()->create(['brand_id' => $brand->id]);

        $response = $this->deleteJson(self::ENDPOINT . "/{$brand->id}");

        $response->assertStatus(409);

        Storage::disk($this->disk())->assertExists($path);
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_list_brands(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    public function test_guest_cannot_create_a_brand(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson(self::ENDPOINT, ['name' => 'Nope'])
            ->assertUnauthorized();
    }

     private function disk(): string
    {
        return config('filesystems.uploads_disk', 's3');
    }
}