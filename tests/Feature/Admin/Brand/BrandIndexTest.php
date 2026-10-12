<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandIndexTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/brands';

    public function test_it_returns_all_brands(): void
    {
        Brand::factory()->count(5)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_it_returns_empty_data_when_no_brands_exist(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('data', []);
    }

    public function test_it_includes_image_fields_in_each_brand(): void
    {
        Brand::factory()->count(2)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'brand_image', 'brand_image_url'],
                ],
            ]);
    }

    public function test_it_returns_stored_image_path_and_url(): void
    {
        Brand::factory()->create([
            'name'        => 'Acme',
            'brand_image' => 'brands/1/abc-123.jpg',
        ]);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $response->assertJsonPath('data.0.brand_image', 'brands/1/abc-123.jpg');

        $url = $response->json('data.0.brand_image_url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('brands/1/abc-123.jpg', $url);
    }

    public function test_it_returns_null_image_fields_when_brand_has_no_image(): void
    {
        Brand::factory()->create(['name' => 'Acme', 'brand_image' => null]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.0.brand_image', null)
            ->assertJsonPath('data.0.brand_image_url', null);
    }

    public function test_it_orders_brands_alphabetically_by_name(): void
    {
        Brand::factory()->create(['name' => 'Charlie']);
        Brand::factory()->create(['name' => 'Alpha']);
        Brand::factory()->create(['name' => 'Bravo']);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $this->assertSame(
            ['Alpha', 'Bravo', 'Charlie'],
            array_column($response->json('data'), 'name'),
        );
    }

    public function test_it_does_not_require_authentication(): void
    {
        Brand::factory()->count(3)->create();

        // No Authorization header, no Sanctum::actingAs — public endpoint.
        $this->getJson(self::ENDPOINT)->assertOk();
    }

    public function test_it_is_read_only_and_does_not_modify_data(): void
    {
        Brand::factory()->count(3)->create();
        $countBefore = Brand::count();
        $namesBefore = Brand::orderBy('id')->pluck('name')->all();

        $this->getJson(self::ENDPOINT)->assertOk();

        $this->assertSame($countBefore, Brand::count());
        $this->assertSame($namesBefore, Brand::orderBy('id')->pluck('name')->all());
    }
}