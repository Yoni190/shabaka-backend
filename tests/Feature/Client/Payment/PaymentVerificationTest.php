<?php

namespace Tests\Feature\Client;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/payments/verify';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.moyasar.secret_key'     => 'sk_test_fake',
            'services.moyasar.base_url'       => 'https://api.moyasar.com/v1',
            'services.moyasar.webhook_secret' => 'whsec_fake',
            'services.moyasar.timeout'        => 5,
        ]);
    }

    private function customer(): array
    {
        $business = Business::factory()->create();
        $user     = User::factory()->create(['business_id' => $business->id]);

        return [$user, $business];
    }

    private function orderFor(Business $business, array $overrides = []): Order
    {
        return Order::factory()->for($business)->create(array_merge([
            'total_amount'   => '500.00',
            'currency'       => 'SAR',
            'status'         => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
        ], $overrides));
    }

    private function fakePayment(array $overrides = []): array
    {
        return array_merge([
            'id'       => 'pay_123',
            'status'   => 'paid',
            'amount'   => 50000,
            'currency' => 'SAR',
            'metadata' => [],
        ], $overrides);
    }

    public function test_paid_payment_marks_order_paid_and_returns_expected_shape(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment())]);

        $response = $this->postJson(self::ENDPOINT, [
            'payment_id' => 'pay_123',
            'order_id'   => $order->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order_number', $order->order_number);

        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_attempts', [
            'order_id'   => $order->id,
            'payment_id' => 'pay_123',
            'status'     => 'paid',
        ]);
    }

    public function test_pending_payment_does_not_mark_order_paid(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(
            $this->fakePayment(['status' => 'initiated'])
        )]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'initiated');

        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
    }

    public function test_failed_payment_does_not_mark_order_paid(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(
            $this->fakePayment(['status' => 'failed'])
        )]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame(PaymentStatus::FAILED, $order->fresh()->payment_status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(
            $this->fakePayment(['amount' => 1])
        )]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_id']);

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(
            $this->fakePayment(['currency' => 'USD'])
        )]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_id']);

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_unknown_payment_id_is_handled_safely(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(['message' => 'not found'], 404)]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_missing', 'order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_id']);
    }

    public function test_provider_outage_returns_503_not_failed(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(['error' => 'down'], 500)]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertStatus(503);

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_missing_configuration_fails_safely(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        config(['services.moyasar.secret_key' => null]);
        Sanctum::actingAs($user);
        Http::fake();

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertStatus(503);

        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => 1])
            ->assertUnauthorized();
    }

    public function test_customer_cannot_verify_another_business_order(): void
    {
        [$user] = $this->customer();
        $otherBusiness = Business::factory()->create();
        $foreignOrder  = $this->orderFor($otherBusiness);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment())]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $foreignOrder->id])
            ->assertNotFound();

        $this->assertSame(PaymentStatus::UNPAID, $foreignOrder->fresh()->payment_status);
    }

    public function test_cancelled_order_cannot_be_paid(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business, ['status' => OrderStatus::CANCELLED]);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment())]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_repeated_verification_is_idempotent(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment())]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertSame(1, PaymentAttempt::where('order_id', $order->id)->count());
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_a_payment_cannot_be_attached_to_a_second_order(): void
    {
        [$user, $business] = $this->customer();
        $first  = $this->orderFor($business);
        $second = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment())]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $first->id])
            ->assertOk();

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $second->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_id']);

        $this->assertSame(PaymentStatus::UNPAID, $second->fresh()->payment_status);
    }

    public function test_metadata_order_id_mismatch_is_rejected(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response(
            $this->fakePayment(['metadata' => ['order_id' => '99999']])
        )]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_id']);
    }

    public function test_initiated_attempt_binds_via_metadata(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);
        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment([
            'metadata' => ['payment_attempt_id' => '1', 'order_id' => (string) $order->id],
        ]))]);

        $initiate = $this->postJson('/api/v1/payments/initiate', ['order_id' => $order->id])
            ->assertCreated()
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.currency', 'SAR');

        $attemptId = $initiate->json('data.payment_attempt_id');

        Http::fake(['api.moyasar.com/*' => Http::response($this->fakePayment([
            'metadata' => ['payment_attempt_id' => (string) $attemptId],
        ]))]);

        $this->postJson(self::ENDPOINT, ['payment_id' => 'pay_123', 'order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame('pay_123', PaymentAttempt::find($attemptId)->payment_id);
    }

    public function test_client_supplied_amount_is_prohibited(): void
    {
        [$user, $business] = $this->customer();
        $order = $this->orderFor($business);

        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'payment_id' => 'pay_123',
            'order_id'   => $order->id,
            'amount'     => 1,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['amount']);
    }
}