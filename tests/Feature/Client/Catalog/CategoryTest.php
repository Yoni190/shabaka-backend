<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/categories';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Access & shape
    |--------------------------------------------------------------------------
    */

    public function test_index_is_public(): void
    {
        $this->getJson(self::ENDPOINT)->assertOk();
    }

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

    public function test_index_returns_empty_when_no_categories_exist(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

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

    public function test_index_paginates_across_pages(): void
    {
        Category::factory()->count(5)->create();

        $pageOne = $this->getJson(self::ENDPOINT . '?per_page=2&page=1');
        $pageTwo = $this->getJson(self::ENDPOINT . '?per_page=2&page=2');
        $pageThree = $this->getJson(self::ENDPOINT . '?per_page=2&page=3');

        $pageOne->assertOk()->assertJsonCount(2, 'data');
        $pageTwo->assertOk()->assertJsonCount(2, 'data');
        $pageThree->assertOk()->assertJsonCount(1, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    public function test_index_searches_by_partial_name_match(): void
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Home Appliances']);
        Category::factory()->create(['name' => 'Books']);

        $response = $this->getJson(self::ENDPOINT . '?search=Elec');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Electronics');
    }

    public function test_index_search_is_case_insensitive_on_sqlite(): void
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Books']);

        // SQLite's LIKE is case-insensitive for ASCII by default.
        $this->getJson(self::ENDPOINT . '?search=electronics')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_search_returns_empty_when_no_match(): void
    {
        Category::factory()->create(['name' => 'Electronics']);

        $this->getJson(self::ENDPOINT . '?search=zzzzzz')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_search_matches_substrings(): void
    {
        Category::factory()->create(['name' => 'Home Appliances']);
        Category::factory()->create(['name' => 'Home Goods']);
        Category::factory()->create(['name' => 'Electronics']);

        $this->getJson(self::ENDPOINT . '?search=Home')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    public function test_index_defaults_to_name_ascending(): void
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

    public function test_index_sorts_by_created_at_ascending(): void
    {
        $oldest = Category::factory()->create(['name' => 'Oldest']);
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        $newest = Category::factory()->create(['name' => 'Newest']);
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->getJson(self::ENDPOINT . '?sort=created_at&direction=asc');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $oldest->id)
            ->assertJsonPath('data.1.id', $newest->id);
    }

    public function test_index_sorts_by_created_at_descending(): void
    {
        $oldest = Category::factory()->create(['name' => 'Oldest']);
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        $newest = Category::factory()->create(['name' => 'Newest']);
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->getJson(self::ENDPOINT . '?sort=created_at&direction=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id);
    }

    public function test_index_ignores_non_sortable_columns(): void
    {
        Category::factory()->create(['name' => 'Zed']);
        Category::factory()->create(['name' => 'Abe']);

        // Falls back to name asc — no error, no SQL injection.
        $response = $this->getJson(self::ENDPOINT . '?sort=password');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Abe');
    }

    /*
    |--------------------------------------------------------------------------
    | Response contents
    |--------------------------------------------------------------------------
    */

    public function test_index_does_not_leak_products_count(): void
    {
        // The public listing should not load the products relation or a count.
        // If the resource later adds products_count, this test flags the change.
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk();

        $this->assertArrayNotHasKey('products_count', $response->json('data.0'));
    }

    /*
    |--------------------------------------------------------------------------
    | Query string preservation
    |--------------------------------------------------------------------------
    */

    public function test_index_pagination_links_include_query_string(): void
    {
        Category::factory()->count(5)->create();

        $response = $this->getJson(self::ENDPOINT . '?per_page=2&search=a');

        $response->assertOk();

        $nextLink = $response->json('links.next');
        $this->assertNotNull($nextLink);
        $this->assertStringContainsString('per_page=2', $nextLink);
        $this->assertStringContainsString('search=a', $nextLink);
    }
}