<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\Client\Payments\MoyasarClient;
use App\Services\Client\Payments\PaymentVerificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoyasarWebhookController extends Controller
{
    public function __construct(
        private readonly MoyasarClient $moyasar,
        private readonly PaymentVerificationService $verification,
    ) {}

    /**
     * Handle a Moyasar webhook
     *
     * Moyasar does not sign webhook payloads with HMAC; the configured
     * secret is echoed back in the body as `secret_token`. A constant-time
     * comparison proves the sender knows the secret, and the payment is
     * then re-fetched from Moyasar before any state change is applied.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.moyasar.webhook_secret');

        if ($secret === '') {
            abort(500, 'Webhook secret is not configured.');
        }

        $provided = (string) $request->input('secret_token', '');

        if (! hash_equals($secret, $provided)) {
            abort(401);
        }

        $eventId   = (string) $request->input('id', '');
        $type      = (string) $request->input('type', '');
        $paymentId = (string) $request->input('data.id', '');

        if ($eventId === '' || $type === '' || $paymentId === '') {
            abort(422, 'Malformed webhook payload.');
        }

        // Idempotency: duplicate deliveries fail the unique index and are
        // acknowledged without reprocessing.
        try {
            $event = PaymentWebhookEvent::create([
                'provider_event_id' => $eventId,
                'type'              => $type,
                'payment_id'        => $paymentId,
                'payload'           => $request->all(),
            ]);
        } catch (QueryException) {
            return response()->json(['message' => 'Already processed']);
        }

        try {
            $this->handle($type, $paymentId);
        } finally {
            $event->update(['processed_at' => now()]);
        }

        return response()->json(['message' => 'ok']);
    }

    private function handle(string $type, string $paymentId): void
    {
        // Never trust the webhook body for amounts or status — re-fetch.
        $payment = $this->moyasar->fetchPayment($paymentId);

        if ($payment === null) {
            return;
        }

        $order = $this->resolveOrder($paymentId, $payment);

        if (! $order) {
            return;
        }

        if (in_array($type, ['payment_paid', 'payment_captured'], true)) {
            $this->verification->apply($order, $payment, $paymentId);

            return;
        }

        // Docs list the failure event as "payment_faild"; the create-webhook
        // example uses "payment_failed". Accept both.
        if (in_array($type, ['payment_failed', 'payment_faild'], true)) {
            $this->verification->applyFailure($order, $payment, $paymentId);
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function resolveOrder(string $paymentId, array $payment): ?Order
    {
        $attempt = PaymentAttempt::where('payment_id', $paymentId)->first();

        if ($attempt) {
            return $attempt->order;
        }

        $orderId = data_get($payment, 'metadata.order_id');

        return $orderId ? Order::find($orderId) : null;
    }
}