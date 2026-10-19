<?php

namespace App\Services\Client\Payments;

use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVerificationService
{
    public function __construct(
        private readonly MoyasarClient $moyasar,
    ) {}

    /**
     * Create a server-side payment attempt for an order.
     *
     * The returned ID should be passed to Moyasar as
     * `metadata[payment_attempt_id]` when the frontend creates the payment,
     * which gives the server a pre-recorded, server-owned association.
     *
     * @throws AuthorizationException
     */
    public function initiate(User $user, int $orderId): PaymentAttempt
    {
        $business = $this->businessFor($user);
        $order    = $business->orders()->findOrFail($orderId);

        $this->assertOrderCanAcceptPayment($order);

        return PaymentAttempt::create([
            'order_id'          => $order->id,
            'payment_id'        => null,
            'expected_amount'   => self::toMinorUnits((string) $order->total_amount),
            'expected_currency' => strtoupper((string) $order->currency),
            'status'            => 'pending',
        ]);
    }

    /**
     * Verify a payment against an order owned by the authenticated business.
     *
     * @return array{status: string, order: Order}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function verifyForUser(User $user, string $paymentId, int $orderId): array
    {
        $business = $this->businessFor($user);
        $order    = $business->orders()->find($orderId);

        if (! $order) {
            abort(404);
        }

        $payment = $this->moyasar->fetchPayment($paymentId);

        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment_id' => ['No payment was found for the supplied identifier.'],
            ]);
        }

        return $this->apply($order, $payment, $paymentId);
    }

    /**
     * Apply a verified payment payload to an order. Shared by the customer
     * verification endpoint and the webhook handler so both paths enforce
     * identical amount, currency, association, and idempotency rules.
     *
     * @param  array<string, mixed>  $payment
     * @return array{status: string, order: Order}
     *
     * @throws ValidationException
     */
    public function apply(Order $order, array $payment, string $paymentId): array
    {
        return DB::transaction(function () use ($order, $payment, $paymentId) {
            /** @var Order $locked */
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertAmountAndCurrency($locked, $payment);
            $this->assertMetadataAssociation($locked, $payment);
            $this->assertOrderCanAcceptPayment($locked);

            $attempt = $this->resolveAttempt($locked, $payment, $paymentId);

            // A payment already applied to a different order can never be
            // reused. The unique index on payment_id is the backstop; this
            // check produces a clean 422 instead of a database error.
            if ($attempt->payment_id !== null && $attempt->payment_id !== $paymentId) {
                throw ValidationException::withMessages([
                    'payment_id' => ['This payment is already associated with another order.'],
                ]);
            }

            if ($attempt->payment_id === null) {
                $attempt->payment_id = $paymentId;
            }

            $mapped = self::mapProviderStatus((string) $payment['status']);

            $attempt->fill([
                'status'          => $mapped,
                'provider_status' => (string) $payment['status'],
            ]);

            if ($mapped === 'paid' && $attempt->paid_at === null) {
                $attempt->paid_at = now();
            }

            if ($mapped === 'failed' && $attempt->failed_at === null) {
                $attempt->failed_at = now();
            }

            $attempt->save();

            $this->applyOrderPaymentStatus($locked, $mapped);

            return [
                'status' => $mapped,
                'order'  => $locked->refresh(),
            ];
        });
    }

    /**
     * Record a failed payment. Never downgrades an order that is already paid.
     *
     * @param  array<string, mixed>  $payment
     *
     * @throws ValidationException
     */
    public function applyFailure(Order $order, array $payment, string $paymentId): void
    {
        DB::transaction(function () use ($order, $payment, $paymentId) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === PaymentStatus::PAID) {
                return;     // out-of-order / stale failure — ignore
            }

            $attempt = $this->resolveAttempt($locked, $payment, $paymentId);

            if ($attempt->payment_id === null) {
                $attempt->payment_id = $paymentId;
            }

            $attempt->fill([
                'status'          => 'failed',
                'provider_status' => (string) ($payment['status'] ?? 'failed'),
                'failed_at'       => $attempt->failed_at ?? now(),
            ])->save();

            if ($locked->payment_status !== PaymentStatus::FAILED) {
                $locked->update(['payment_status' => PaymentStatus::FAILED]);
            }
        });
    }

    /**
     * Locate the existing attempt for this payment, or bind an attempt the
     * frontend pre-created via /payments/initiate (identified through
     * Moyasar metadata), or create a new attempt as a last resort.
     *
     * @param  array<string, mixed>  $payment
     *
     * @throws ValidationException
     */
    private function resolveAttempt(Order $order, array $payment, string $paymentId): PaymentAttempt
    {
        $attempt = PaymentAttempt::where('payment_id', $paymentId)->first();

        if ($attempt) {
            if ($attempt->order_id !== $order->id) {
                throw ValidationException::withMessages([
                    'payment_id' => ['This payment is already associated with another order.'],
                ]);
            }

            return $attempt;
        }

        $attemptId = data_get($payment, 'metadata.payment_attempt_id');

        if ($attemptId) {
            /** @var PaymentAttempt|null $pre */
            $pre = PaymentAttempt::whereKey($attemptId)->lockForUpdate()->first();

            if ($pre) {
                if ($pre->order_id !== $order->id) {
                    throw ValidationException::withMessages([
                        'payment_id' => ['The payment attempt does not belong to this order.'],
                    ]);
                }

                if ($pre->payment_id !== null && $pre->payment_id !== $paymentId) {
                    throw ValidationException::withMessages([
                        'payment_id' => ['This payment attempt is already bound to a different payment.'],
                    ]);
                }

                return $pre;
            }
        }

        try {
            return PaymentAttempt::create([
                'order_id'          => $order->id,
                'payment_id'        => $paymentId,
                'expected_amount'   => self::toMinorUnits((string) $order->total_amount),
                'expected_currency' => strtoupper((string) $order->currency),
                'status'            => 'pending',
            ]);
        } catch (QueryException $e) {
            // Concurrent verification of the same payment won the insert.
            return PaymentAttempt::where('payment_id', $paymentId)->firstOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     *
     * @throws ValidationException
     */
    private function assertAmountAndCurrency(Order $order, array $payment): void
    {
        $expectedMinor = self::toMinorUnits((string) $order->total_amount);
        $actualMinor   = (int) $payment['amount'];

        if ($actualMinor !== $expectedMinor) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment amount does not match the order total.'],
            ]);
        }

        if (strtoupper((string) $payment['currency']) !== strtoupper((string) $order->currency)) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment currency does not match the order currency.'],
            ]);
        }
    }

    /**
     * If the payment carries an `order_id` in metadata, it must match. Absent
     * metadata is accepted — amount + currency + payment uniqueness still
     * bind the payment to this order.
     *
     * @param  array<string, mixed>  $payment
     *
     * @throws ValidationException
     */
    private function assertMetadataAssociation(Order $order, array $payment): void
    {
        $metadataOrderId = data_get($payment, 'metadata.order_id');

        if ($metadataOrderId !== null && (string) $metadataOrderId !== (string) $order->id) {
            throw ValidationException::withMessages([
                'payment_id' => ['The payment does not reference this order.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertOrderCanAcceptPayment(Order $order): void
    {
        if ($order->status->value === 'cancelled') {
            throw ValidationException::withMessages([
                'order_id' => ['A cancelled order cannot be paid.'],
            ]);
        }
    }

    private function applyOrderPaymentStatus(Order $order, string $mapped): void
    {
        $target = match ($mapped) {
            'paid'                  => PaymentStatus::PAID,
            'failed'                => $order->payment_status === PaymentStatus::PAID
                ? PaymentStatus::PAID
                : PaymentStatus::FAILED,
            'initiated', 'authorized', 'pending' => PaymentStatus::PENDING,
            default                 => $order->payment_status,
        };

        if ($order->payment_status !== $target) {
            $order->update(['payment_status' => $target]);
        }
    }

    /**
     * Map Moyasar's provider status to the frontend's supported set.
     *
     * `captured` means authorized funds were actually collected → paid.
     * `refunded` and `voided` are not representable in the frontend union,
     * so they collapse to `failed`; the order's payment_status is handled
     * separately above.
     */
    private static function mapProviderStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'paid', 'captured' => 'paid',
            'failed', 'refunded', 'voided' => 'failed',
            'authorized' => 'authorized',
            'initiated' => 'initiated',
            'verified' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Convert a `decimal:2` string such as "500.00" to integer minor units
     * (50000) without touching floating point.
     */
    private static function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');

        return ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * @throws AuthorizationException
     */
    private function businessFor(User $user): Business
    {
        $business = $user->business;

        if (! $business) {
            throw new AuthorizationException('Your account is not associated with a business.');
        }

        return $business;
    }
}