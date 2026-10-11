<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/dashboard';

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Response shape
    |--------------------------------------------------------------------------
    */

    public function test_index_returns_expected_structure(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'catalogue' => [
                        'total_products',
                        'active_products',
                        'inactive_products',
                        'total_categories',
                        'total_brands',
                        'out_of_stock_products',
                    ],
                    'customers' => [
                        'total_business_customers',
                        'recently_registered',
                    ],
                    'recent_customers',
                ],
            ]);
    }

    public function test_index_returns_zeroes_on_an_empty_database(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.catalogue.total_products', 0)
            ->assertJsonPath('data.catalogue.active_products', 0)
            ->assertJsonPath('data.catalogue.inactive_products', 0)
            ->assertJsonPath('data.catalogue.total_categories', 0)
            ->assertJsonPath('data.catalogue.total_brands', 0)
            ->assertJsonPath('data.catalogue.out_of_stock_products', 0)
            ->assertJsonPath('data.customers.total_business_customers', 0)
            ->assertJsonPath('data.customers.recently_registered', 0)
            ->assertJsonPath('data.recent_customers', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Catalogue
    |--------------------------------------------------------------------------
    */

    public function test_catalogue_counts_products_by_active_status(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.catalogue.total_products', 5)
            ->assertJsonPath('data.catalogue.active_products', 3)
            ->assertJsonPath('data.catalogue.inactive_products', 2);
    }

    public function test_catalogue_counts_out_of_stock_products(): void
    {
        Product::factory()->count(2)->create(['stock_quantity' => 0]);
        Product::factory()->create(['stock_quantity' => 10]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.catalogue.out_of_stock_products', 2);
    }

    public function test_catalogue_counts_categories_and_brands(): void
    {
        Category::factory()->count(4)->create();
        Brand::factory()->count(7)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.catalogue.total_categories', 4)
            ->assertJsonPath('data.catalogue.total_brands', 7);
    }

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    public function test_total_business_customers_counts_only_users_with_a_business(): void
    {
        // Two business customers (each attached to a business).
        User::factory()->businessCustomer()->forBusiness()->count(2)->create();

        // One plain user without a business.
        User::factory()->create(['business_id' => null]);

        // The admin that setUp() authenticated — no business.
        // (Total admins in DB: 1, but they should be excluded.)

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.customers.total_business_customers', 2);
    }

    public function test_recently_registered_counts_businesses_created_in_the_last_30_days(): void
    {
        Business::factory()->count(3)->create(); // created "now"

        Carbon::setTestNow(now()->subDays(60));
        Business::factory()->count(2)->create(); // 60 days ago
        Carbon::setTestNow(); // reset

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.customers.recently_registered', 3);
    }

    public function test_recently_registered_includes_businesses_exactly_at_the_30_day_boundary(): void
    {
        $thirtyDaysAgo = now()->subDays(30)->addMinute();

        Carbon::setTestNow($thirtyDaysAgo);
        Business::factory()->create();
        Carbon::setTestNow();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.customers.recently_registered', 1);
    }

    public function test_recently_registered_excludes_businesses_just_outside_the_window(): void
    {
        Carbon::setTestNow(now()->subDays(31));
        Business::factory()->create();
        Carbon::setTestNow();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.customers.recently_registered', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Recent customers
    |--------------------------------------------------------------------------
    */

    public function test_recent_customers_only_includes_users_with_a_business(): void
    {
        User::factory()->businessCustomer()->forBusiness()->create(['name' => 'Business User']);
        User::factory()->create(['name' => 'Plain User', 'business_id' => null]);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(1, 'data.recent_customers')
            ->assertJsonPath('data.recent_customers.0.name', 'Business User');
    }

    public function test_recent_customers_is_limited_to_five(): void
    {
        User::factory()->businessCustomer()->forBusiness()->count(8)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonCount(5, 'data.recent_customers');
    }

    public function test_recent_customers_is_ordered_by_latest_first(): void
    {
        $oldest = User::factory()->businessCustomer()->forBusiness()->create();
        $oldest->forceFill(['created_at' => now()->subDays(10)])->save();

        $middle = User::factory()->businessCustomer()->forBusiness()->create();
        $middle->forceFill(['created_at' => now()->subDays(5)])->save();

        $newest = User::factory()->businessCustomer()->forBusiness()->create();
        $newest->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.recent_customers.0.id', $newest->id)
            ->assertJsonPath('data.recent_customers.1.id', $middle->id)
            ->assertJsonPath('data.recent_customers.2.id', $oldest->id);
    }

    public function test_recent_customers_include_the_business_relation(): void
    {
        $business = Business::factory()->create(['name' => 'Acme Inc']);
        User::factory()->businessCustomer()->forBusiness($business)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('data.recent_customers.0.business.id', $business->id)
            ->assertJsonPath('data.recent_customers.0.business.name', 'Acme Inc');
    }

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_view_the_dashboard(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_view_the_dashboard(): void
    {
        $user = User::factory()->businessCustomer()->forBusiness()->create();
        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)->assertForbidden();
    }
}