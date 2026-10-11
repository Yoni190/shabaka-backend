<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Auth\Concerns\HasAuthEndpoints;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase, HasAuthEndpoints;

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->businessCustomer()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->userEndpoint());

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_authenticated_user_can_view_business_relation(): void
    {
        $business = Business::factory()->create();
        $user     = User::factory()->businessCustomer()->forBusiness($business)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->userEndpoint());

        $response->assertOk()
            ->assertJsonPath('business.id', $business->id);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson($this->userEndpoint());

        $response->assertUnauthorized();
    }

    public function test_admin_can_view_admin_profile(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson($this->adminUserEndpoint());

        $response->assertOk()
            ->assertJsonPath('id', $admin->id);
    }

    public function test_non_admin_cannot_view_admin_profile(): void
    {
        $user = User::factory()->businessCustomer()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson($this->adminUserEndpoint());

        $response->assertForbidden();
    }
}