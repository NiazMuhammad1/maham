<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks that the customer really paid, using PAYMENT_DRIVER in .env:
 * "fake" (local / staging, every payment succeeds) or "live" (real gateways).
 *
 * Card / Apple Pay (Moyasar):
 * 1. The app shows the card form / Apple Pay button with the Moyasar Flutter SDK
 *    (publishable key + amount in halalas). The SDK charges the customer and returns a payment id.
 * 2. The app sends that payment id to "Accept offer".
 * 3. We ask Moyasar (with the secret key) for that payment and only confirm the booking
 *    when it is paid, for the right amount, in SAR.
 *
 * Tabby / Tamara (buy now, pay later):
 * 1. The app calls "Start checkout" and we open a checkout at Tabby / Tamara. We return its payment id and page link.
 * 2. The app opens the page in a WebView. When the WebView reaches the success link, the app
 *    sends the payment id to "Accept offer".
 * 3. We check the payment is approved for this offer and amount, then capture it (take the money).
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
    public function verify(string $method, ?string $paymentId, float $amount, string $currency, string $reference): array
    {
        if ($this->isFake()) {
            return [
                'gateway' => 'fake',
                'transaction_id' => $paymentId ?: 'fake_'.Str::uuid(),
                'response' => ['status' => 'paid', 'note' => 'Fake payment (PAYMENT_DRIVER=fake)'],
            ];
        }

        return match ($method) {
            'tabby' => $this->verifyWithTabby((string) $paymentId, $amount, $currency, $reference),
            'tamara' => $this->verifyWithTamara((string) $paymentId, $amount, $currency, $reference),
            default => $this->verifyWithMoyasar((string) $paymentId, $amount, $currency),
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
     * Methods to show on the Payments sheet. Tabby / Tamara only when their keys are set.
     *
     * @return list<string>
     */
    public function availableMethods(): array
    {
        return array_values(array_filter(Payment::METHODS, fn (string $method) => match ($method) {
            'tabby' => $this->isFake() || filled(config('services.payment.tabby.secret_key')),
            'tamara' => $this->isFake() || filled(config('services.payment.tamara.api_token')),
            default => true,
        }));
    }

    /**
     * Open a Tabby / Tamara checkout for this offer.
     *
     * @return array{payment_id: string, checkout_url: string}
     *
     * @throws ValidationException when the gateway does not offer installments for this customer / amount
     */
    public function createCheckout(string $method, Offer $offer, User $customer, float $amount): array
    {
        if ($this->isFake()) {
            // Go straight to the success link, so the app's WebView flow can be tested without keys
            $paymentId = "fake_{$method}_".Str::uuid();

            return [
                'payment_id' => $paymentId,
                'checkout_url' => config('services.payment.redirect_urls.success').'?payment_id='.$paymentId,
            ];
        }

        $checkout = $method === 'tabby'
            ? $this->createTabbyCheckout($offer, $customer, $amount)
            : $this->createTamaraCheckout($offer, $customer, $amount);

        if (blank($checkout['payment_id']) || blank($checkout['checkout_url'])) {
            throw ValidationException::withMessages(['payment_method' => __('messages.installment_not_available')]);
        }

        return $checkout;
    }

    /**
     * Our order number at the gateway. Used to make sure a payment id belongs to this offer.
     */
    public static function reference(Offer $offer): string
    {
        return 'OFFER-'.$offer->id;
    }

    private function isFake(): bool
    {
        return config('services.payment.driver') === 'fake';
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

    /**
     * Tabby (tabby.ai) - split in 4 payments.
     *
     * @return array{payment_id: ?string, checkout_url: ?string}
     */
    private function createTabbyCheckout(Offer $offer, User $customer, float $amount): array
    {
        $serviceRequest = $offer->serviceRequest;

        $response = $this->tabby()->post('/api/v2/checkout', [
            'payment' => [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $offer->currency,
                'description' => $serviceRequest->service->name,
                'buyer' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->fullPhone(),
                ],
                'buyer_history' => [
                    'registered_since' => $customer->created_at->toIso8601String(),
                    'loyalty_level' => $customer->bookings()->count(),
                ],
                'shipping_address' => [
                    'city' => $serviceRequest->city?->name,
                    'address' => $serviceRequest->street_address,
                    'zip' => $serviceRequest->postal_code,
                ],
                'order' => [
                    'reference_id' => self::reference($offer),
                    'items' => [[
                        'title' => $serviceRequest->service->name,
                        'quantity' => 1,
                        'unit_price' => number_format($amount, 2, '.', ''),
                        'category' => 'Home Services',
                        'reference_id' => self::reference($offer),
                    ]],
                ],
                'order_history' => [],
            ],
            'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'merchant_code' => config('services.payment.tabby.merchant_code'),
            'merchant_urls' => config('services.payment.redirect_urls'),
        ]);

        // Tabby answers "rejected" when installments are not available for this customer / amount
        if ($response->failed() || $response->json('status') !== 'created') {
            throw ValidationException::withMessages(['payment_method' => __('messages.installment_not_available')]);
        }

        return [
            'payment_id' => $response->json('payment.id'),
            'checkout_url' => $response->json('configuration.available_products.installments.0.web_url'),
        ];
    }

    /**
     * @return array{gateway: string, transaction_id: string, response: array<string, mixed>}
     */
    private function verifyWithTabby(string $paymentId, float $amount, string $currency, string $reference): array
    {
        $response = $this->tabby()->get('/api/v2/payments/'.urlencode($paymentId));
        $payment = $response->json() ?? [];

        // AUTHORIZED = the customer finished the Tabby checkout, the money is not taken yet
        $isApproved = $response->successful()
            && ($payment['status'] ?? null) === 'AUTHORIZED'
            && $this->sameAmount($payment['amount'] ?? 0, $amount)
            && strtoupper($payment['currency'] ?? '') === strtoupper($currency)
            && ($payment['order']['reference_id'] ?? null) === $reference;

        $isCaptured = $isApproved && $this->tabby()
            ->post('/api/v2/payments/'.urlencode($paymentId).'/captures', ['amount' => number_format($amount, 2, '.', '')])
            ->successful();

        if (! $isCaptured) {
            throw ValidationException::withMessages(['payment_id' => __('messages.payment_failed')]);
        }

        return [
            'gateway' => 'tabby',
            'transaction_id' => $paymentId,
            'response' => $payment,
        ];
    }

    /**
     * Tamara (tamara.co) - split in installments.
     *
     * @return array{payment_id: ?string, checkout_url: ?string}
     */
    private function createTamaraCheckout(Offer $offer, User $customer, float $amount): array
    {
        $serviceRequest = $offer->serviceRequest;
        $total = ['amount' => $amount, 'currency' => $offer->currency];
        $firstName = Str::before($customer->name, ' ');
        $lastName = Str::after($customer->name, ' ') ?: $firstName;

        $response = $this->tamara()->post('/checkout', [
            'order_reference_id' => self::reference($offer),
            'total_amount' => $total,
            'description' => $serviceRequest->service->name,
            'country_code' => 'SA',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'locale' => app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            'items' => [[
                'reference_id' => self::reference($offer),
                'type' => 'Service',
                'name' => $serviceRequest->service->name,
                'sku' => $serviceRequest->service->slug,
                'quantity' => 1,
                'total_amount' => $total,
            ]],
            'consumer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $customer->fullPhone(),
                'email' => $customer->email,
            ],
            'shipping_address' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'line1' => $serviceRequest->street_address,
                'city' => $serviceRequest->city?->name,
                'country_code' => 'SA',
            ],
            'tax_amount' => ['amount' => 0, 'currency' => $offer->currency],
            'shipping_amount' => ['amount' => 0, 'currency' => $offer->currency],
            'merchant_url' => config('services.payment.redirect_urls'),
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['payment_method' => __('messages.installment_not_available')]);
        }

        return [
            'payment_id' => $response->json('order_id'),
            'checkout_url' => $response->json('checkout_url'),
        ];
    }

    /**
     * @return array{gateway: string, transaction_id: string, response: array<string, mixed>}
     */
    private function verifyWithTamara(string $orderId, float $amount, string $currency, string $reference): array
    {
        $response = $this->tamara()->get('/orders/'.urlencode($orderId));
        $order = $response->json() ?? [];

        // "approved" = the customer finished the Tamara checkout; we must authorise it, then capture it
        $isApproved = $response->successful()
            && in_array($order['status'] ?? null, ['approved', 'authorised'], true)
            && $this->sameAmount($order['total_amount']['amount'] ?? 0, $amount)
            && strtoupper($order['total_amount']['currency'] ?? '') === strtoupper($currency)
            && ($order['order_reference_id'] ?? null) === $reference;

        if ($isApproved && $order['status'] === 'approved') {
            $isApproved = $this->tamara()->post('/orders/'.urlencode($orderId).'/authorise')->successful();
        }

        $isCaptured = $isApproved && $this->tamara()->post('/payments/capture', [
            'order_id' => $orderId,
            'total_amount' => ['amount' => $amount, 'currency' => $currency],
            'shipping_info' => ['shipped_at' => now()->toIso8601String(), 'shipping_company' => 'Maham'],
        ])->successful();

        if (! $isCaptured) {
            throw ValidationException::withMessages(['payment_id' => __('messages.payment_failed')]);
        }

        return [
            'gateway' => 'tamara',
            'transaction_id' => $orderId,
            'response' => $order,
        ];
    }

    private function tabby(): PendingRequest
    {
        return Http::connectTimeout(5)
            ->timeout(15)
            ->baseUrl((string) config('services.payment.tabby.base_url'))
            ->withToken((string) config('services.payment.tabby.secret_key'));
    }

    private function tamara(): PendingRequest
    {
        return Http::connectTimeout(5)
            ->timeout(15)
            ->baseUrl((string) config('services.payment.tamara.base_url'))
            ->withToken((string) config('services.payment.tamara.api_token'));
    }

    /**
     * Gateways send amounts as "300.00" or 300; compare in halalas.
     */
    private function sameAmount(mixed $gatewayAmount, float $amount): bool
    {
        return (int) round((float) $gatewayAmount * 100) === (int) round($amount * 100);
    }
}
