<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\InitiatePaymentRequest;
use App\Http\Requests\Client\VerifyPaymentRequest;
use App\Http\Resources\PaymentVerificationResource;
use App\Services\Client\Payments\PaymentVerificationService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentVerificationService $verification,
    ) {}

    /**
     * Initiate a payment attempt
     *
     * Creates a server-side payment attempt for an order and returns the
     * attempt ID, amount, and currency. Pass the attempt ID to Moyasar as
     * `metadata[payment_attempt_id]` when creating the payment so the server
     * has a pre-recorded, server-owned association to verify against.
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $attempt = $this->verification->initiate(
            $request->user(),
            (int) $request->validated('order_id'),
        );

        return response()->json([
            'data' => [
                'payment_attempt_id' => $attempt->id,
                'order_id'           => $attempt->order_id,
                'amount'             => $attempt->expected_amount,
                'currency'           => $attempt->expected_currency,
            ],
        ], 201);
    }

    /**
     * Verify a payment
     *
     * Fetches the payment from Moyasar using the server-side secret key,
     * verifies its status, amount, currency, and order association, and
     * updates the order's payment status accordingly. Idempotent for
     * repeated verifications of the same successful payment.
     */
    public function verify(VerifyPaymentRequest $request): PaymentVerificationResource
    {
        $result = $this->verification->verifyForUser(
            $request->user(),
            (string) $request->validated('payment_id'),
            (int) $request->validated('order_id'),
        );

        return new PaymentVerificationResource($result);
    }
}