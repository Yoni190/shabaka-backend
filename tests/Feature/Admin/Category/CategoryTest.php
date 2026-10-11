<?php

namespace Tests\Feature\Admin\Category;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/categories';

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

    public function test_index_returns_paginated_categories(): void
    {
        Category::factory()->count(3)->create();

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
        Category::factory()->count(5)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_caps_per_page_at_100(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_defaults_per_page_when_missing(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_defaults_per_page_when_invalid(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=abc');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_searches_by_name(): void
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Home Appliances']);
        Category::factory()->create(['name' => 'Books']);

        $response = $this->getJson(self::ENDPOINT . '?search=Elec');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Electronics');
    }

    public function test_index_sorts_by_name_ascending_by_default(): void
    {
        Category::factory()->create(['name' => 'Charlie']);
        Category::factory()->create(['name' => 'Alpha']);
        Category::factory()->create(['name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    public function test_index_sorts_by_name_descending(): void
    {
        Category::factory()->create(['name' => 'Alpha']);
        Category::factory()->create(['name' => 'Charlie']);
        Category::factory()->create(['name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT . '?sort=name&direction=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Charlie')
            ->assertJsonPath('data.1.name', 'Bravo')
            ->assertJsonPath('data.2.name', 'Alpha');
    }

    public function test_index_ignores_non_sortable_columns(): void
    {
        Category::factory()->create(['name' => 'Zed']);
        Category::factory()->create(['name' => 'Abe']);

        // `description` isn't in SORTABLE — should silently fall back to name asc.
        $response = $this->getJson(self::ENDPOINT . '?sort=description');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Abe');
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_store_creates_a_category(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Electronics',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Electronics')
            ->assertJsonPath('message', 'Category created successfully');

        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson(self::ENDPOINT, []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Electronics']);

        $response = $this->postJson(self::ENDPOINT, [
            'name' => 'Electronics',
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

    public function test_show_returns_a_category_with_product_count(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->getJson(self::ENDPOINT . "/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.products_count', 3);
    }

    public function test_show_returns_zero_product_count_when_category_is_empty(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson(self::ENDPOINT . "/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.products_count', 0);
    }

    public function test_show_returns_404_for_missing_category(): void
    {
        $response = $this->getJson(self::ENDPOINT . '/99999');

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_update_modifies_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson(self::ENDPOINT . "/{$category->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('message', 'Category updated successfully');

        $this->assertDatabaseHas('categories', [
            'id'   => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_allows_keeping_the_same_name(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);

        $response = $this->putJson(self::ENDPOINT . "/{$category->id}", [
            'name' => 'Electronics',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Electronics');
    }

    public function test_update_rejects_name_taken_by_another_category(): void
    {
        Category::factory()->create(['name' => 'Taken']);
        $category = Category::factory()->create(['name' => 'Mine']);

        $response = $this->putJson(self::ENDPOINT . "/{$category->id}", [
            'name' => 'Taken',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validates_required_name(): void
    {
        $category = Category::factory()->create();

        $response = $this->putJson(self::ENDPOINT . "/{$category->id}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_destroy_deletes_an_empty_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson(self::ENDPOINT . "/{$category->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Category deleted successfully']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_409_when_category_has_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson(self::ENDPOINT . "/{$category->id}");

        $response->assertStatus(409);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_404_for_missing_category(): void
    {
        $response = $this->deleteJson(self::ENDPOINT . '/99999');

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_list_categories(): void
    {
        // Drop the admin that setUp() authenticated by resetting auth state.
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    public function test_guest_cannot_create_a_category(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson(self::ENDPOINT, ['name' => 'Nope'])
            ->assertUnauthorized();
    }
}