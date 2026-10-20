<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks that the customer really paid, using the gateway chosen by PAYMENT_DRIVER in .env:
 * "fake" (local / staging, every payment succeeds) or "moyasar" (live).
 *
 * How the payment works with Moyasar:
 * 1. The app shows the card form / Apple Pay button with the Moyasar Flutter SDK
 *    (publishable key + amount in halalas). The SDK charges the customer and returns a payment id.
 * 2. The app sends that payment id to "Accept offer".
 * 3. We ask Moyasar (with the secret key) for that payment and only confirm the booking
 *    when it is paid, for the right amount, in SAR.
 */
class PaymentService
{
    /**
     * Check the payment and return its details for the payments table.
     *
     * @return array{gateway: string, transaction_id: string, response: array<string, mixed>}
     *
     * @throws ValidationException when the payment is missing, not paid, or for a different amount
     */
    public function verify(?string $paymentId, float $amount, string $currency): array
    {
        return match (config('services.payment.driver')) {
            'moyasar' => $this->verifyWithMoyasar((string) $paymentId, $amount, $currency),
            default => [
                'gateway' => 'fake',
                'transaction_id' => $paymentId ?: 'fake_'.Str::uuid(),
                'response' => ['status' => 'paid', 'note' => 'Fake payment (PAYMENT_DRIVER=fake)'],
            ],
        };
    }

    /**
     * Data the app needs to open the Moyasar payment form.
     *
     * @return array{gateway: string, publishable_key: ?string, apple_pay_merchant_id: ?string}
     */
    public function clientConfig(): array
    {
        return [
            'gateway' => (string) config('services.payment.driver'),
            'publishable_key' => config('services.payment.moyasar.publishable_key'),
            'apple_pay_merchant_id' => config('services.payment.moyasar.apple_pay_merchant_id'),
        ];
    }

    /**
     * Moyasar (moyasar.com) - Saudi payment gateway (mada, Visa, Mastercard, Apple Pay).
     *
     * @return array{gateway: string, transaction_id: string, response: array<string, mixed>}
     */
    private function verifyWithMoyasar(string $paymentId, float $amount, string $currency): array
    {
        $response = Http::connectTimeout(5)
            ->timeout(15)
            ->withBasicAuth((string) config('services.payment.moyasar.secret_key'), '')
            ->get('https://api.moyasar.com/v1/payments/'.urlencode($paymentId));

        $payment = $response->json() ?? [];

        // Moyasar amounts are in halalas (SAR 250.00 = 25000)
        $isPaid = $response->successful()
            && in_array($payment['status'] ?? null, ['paid', 'captured'], true)
            && (int) ($payment['amount'] ?? 0) === (int) round($amount * 100)
            && strtoupper($payment['currency'] ?? '') === strtoupper($currency);

        if (! $isPaid) {
            throw ValidationException::withMessages(['payment_id' => __('messages.payment_failed')]);
        }

        return [
            'gateway' => 'moyasar',
            'transaction_id' => $paymentId,
            'response' => $payment,
        ];
    }
}
