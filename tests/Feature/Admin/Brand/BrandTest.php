<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/brands';

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
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
}