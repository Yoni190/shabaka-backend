<?php

namespace Tests\Feature\Webhooks;

use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoyasarWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/webhooks/moyasar';
    private const SECRET   = 'whsec_fake';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.moyasar.secret_key'     => 'sk_test_fake',
            'services.moyasar.base_url'       => 'https://api.moyasar.com/v1',
            'services.moyasar.webhook_secret' => self::SECRET,
        ]);
    }

    private function order(array $overrides = []): Order
    {
        $business = Business::factory()->create();

        return Order::factory()->for($business)->create(array_merge([
            'total_amount'   => '500.00',
            'currency'       => 'SAR',
            'payment_status' => PaymentStatus::UNPAID,
        ], $overrides));
    }

    private function payload(string $type, string $paymentId, array $overrides = []): array
    {
        return array_merge([
            'id'           => 'evt_'.uniqid(),
            'type'         => $type,
            'secret_token' => self::SECRET,
            'live'         => false,
            'data'         => ['id' => $paymentId, 'status' => 'paid', 'amount' => 50000, 'currency' => 'SAR'],
        ], $overrides);
    }

    public function test_valid_paid_event_marks_order_paid(): void
    {
        $order = $this->order();

        Http::fake(['api.moyasar.com/*' => Http::response([
            'id' => 'pay_123', 'status' => 'paid', 'amount' => 50000, 'currency' => 'SAR',
            'metadata' => ['order_id' => (string) $order->id],
        ])]);

        $this->postJson(self::ENDPOINT, $this->payload('payment_paid', 'pay_123'))
            ->assertOk();

        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_invalid_secret_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload('payment_paid', 'pay_123', [
            'secret_token' => 'wrong',
        ]))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_missing_secret_configuration_returns_500(): void
    {
        config(['services.moyasar.webhook_secret' => null]);

        $this->postJson(self::ENDPOINT, $this->payload('payment_paid', 'pay_123'))
            ->assertStatus(500);
    }

    public function test_duplicate_event_is_acknowledged_without_reprocessing(): void
    {
        $order = $this->order();
        $event = $this->payload('payment_paid', 'pay_123');

        Http::fake(['api.moyasar.com/*' => Http::response([
            'id' => 'pay_123', 'status' => 'paid', 'amount' => 50000, 'currency' => 'SAR',
            'metadata' => ['order_id' => (string) $order->id],
        ])]);

        $this->postJson(self::ENDPOINT, $event)->assertOk();
        $this->postJson(self::ENDPOINT, $event)->assertOk();

        $this->assertSame(1, PaymentWebhookEvent::count());
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_failed_event_does_not_downgrade_paid_order(): void
    {
        $order = $this->order(['payment_status' => PaymentStatus::PAID]);

        Http::fake(['api.moyasar.com/*' => Http::response([
            'id' => 'pay_123', 'status' => 'failed', 'amount' => 50000, 'currency' => 'SAR',
            'metadata' => ['order_id' => (string) $order->id],
        ])]);

        $this->postJson(self::ENDPOINT, $this->payload('payment_failed', 'pay_123'))
            ->assertOk();

        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_amount_mismatch_prevents_state_change(): void
    {
        $order = $this->order();

        Http::fake(['api.moyasar.com/*' => Http::response([
            'id' => 'pay_123', 'status' => 'paid', 'amount' => 1, 'currency' => 'SAR',
            'metadata' => ['order_id' => (string) $order->id],
        ])]);

        $this->postJson(self::ENDPOINT, $this->payload('payment_paid', 'pay_123'))
            ->assertUnprocessable();

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }

    public function test_unknown_payment_reference_is_ignored_safely(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['message' => 'not found'], 404)]);

        $this->postJson(self::ENDPOINT, $this->payload('payment_paid', 'pay_missing'))
            ->assertOk();

        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_malformed_payload_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, ['secret_token' => self::SECRET])
            ->assertStatus(422);
    }

    public function test_provider_failure_does_not_mark_order_paid(): void
    {
        $order = $this->order();

        Http::fake(['api.moyasar.com/*' => Http::response(['error' => 'down'], 500)]);

        $this->postJson(self::ENDPOINT, $this->payload('payment_paid', 'pay_123'))
            ->assertStatus(503);

        $this->assertSame(PaymentStatus::UNPAID, $order->fresh()->payment_status);
    }
}