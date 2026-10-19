<?php

namespace App\Services\Client\Payments;

use App\Exceptions\PaymentVerificationUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MoyasarClient
{
    /**
     * Fetch a payment from Moyasar using the server-side secret key.
     *
     * Returns null only for a definitive 404 (unknown payment). Every other
     * failure — missing config, auth error, timeout, 5xx — throws a 503 so
     * the caller never downgrades an order based on an infrastructure fault.
     *
     * @return array<string, mixed>|null
     *
     * @throws PaymentVerificationUnavailableException
     */
    public function fetchPayment(string $paymentId): ?array
    {
        $key = (string) config('services.moyasar.secret_key');

        if ($key === '') {
            throw new PaymentVerificationUnavailableException(
                'Payment provider is not configured.',
            );
        }

        $url = rtrim((string) config('services.moyasar.base_url', 'https://api.moyasar.com/v1'), '/')
            .'/payments/'.rawurlencode($paymentId);

        try {
            $response = Http::withBasicAuth($key, '')
                ->acceptJson()
                ->timeout((int) config('services.moyasar.timeout', 10))
                ->get($url);
        } catch (ConnectionException|RequestException $e) {
            // Do not log the request headers — they contain the secret key.
            report(new \RuntimeException('Moyasar fetch failed: '.$e->getMessage()));

            throw new PaymentVerificationUnavailableException();
        }

        if ($response->status() === 404) {
            return null;
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new PaymentVerificationUnavailableException(
                'Payment provider credentials are invalid.',
            );
        }

        if (! $response->successful()) {
            throw new PaymentVerificationUnavailableException();
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['id'], $payload['status'], $payload['amount'], $payload['currency'])) {
            throw new PaymentVerificationUnavailableException();
        }

        return $payload;
    }
}