<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_offers_received_on_their_request(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->for($customer, 'customer')->create();
        Offer::factory()->for($serviceRequest)->count(2)->create();
        Offer::factory()->for($serviceRequest)->create(['status' => Offer::STATUS_WITHDRAWN]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/service-requests/{$serviceRequest->id}/offers")
            ->assertOk()
            ->assertJsonPath('data.service_request.id', $serviceRequest->id)
            ->assertJsonCount(2, 'data.offers')
            ->assertJsonPath('data.offers.0.price', 250)
            ->assertJsonPath('data.offers.0.provider.tagline', 'Professional AC and Electronic Services');
    }

    public function test_customer_cannot_see_offers_on_someone_elses_request(): void
    {
        $offer = Offer::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/service-requests/{$offer->service_request_id}/offers")->assertNotFound();
        $this->getJson("/api/v1/offers/{$offer->id}")->assertNotFound();
    }

    public function test_offer_details_use_the_providers_proposed_date(): void
    {
        $offer = Offer::factory()->proposed('2026-12-20', '14:30')->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->getJson("/api/v1/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.date', '2026-12-20')
            ->assertJsonPath('data.time_label', '02:30 PM')
            ->assertJsonPath('data.can_accept', true)
            ->assertJsonPath('data.payment.service_price', 250)
            ->assertJsonPath('data.payment.processing_fee', 12.5)
            ->assertJsonPath('data.payment.amount', 262.5)
            ->assertJsonPath('data.payment.amount_halalas', 26250)
            ->assertJsonPath('data.payment.methods.1.value', 'apple_pay')
            ->assertJsonPath('data.service_request.id', $offer->service_request_id);
    }

    public function test_accepting_an_offer_pays_and_creates_the_booking(): void
    {
        $offer = Offer::factory()->create(['price' => 300]);
        $otherOffer = Offer::factory()->for($offer->serviceRequest)->create();
        $customer = $offer->serviceRequest->customer;

        Sanctum::actingAs($customer);

        $response = $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'card']);

        $response->assertCreated()
            ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('data.service_price', 300)
            ->assertJsonPath('data.processing_fee', 15)
            ->assertJsonPath('data.total_amount', 315)
            ->assertJsonPath('data.payment_method', 'card')
            ->assertJsonPath('data.provider.id', $offer->provider_id);

        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame(Offer::STATUS_REJECTED, $otherOffer->fresh()->status);
        $this->assertSame(ServiceRequest::STATUS_ACCEPTED, $offer->serviceRequest->fresh()->status);
        $this->assertSame($offer->id, $offer->serviceRequest->fresh()->accepted_offer_id);
        $this->assertDatabaseHas('payments', ['amount' => 315, 'status' => 'paid', 'method' => 'card']);
        $this->assertDatabaseHas('bookings', ['service_price' => 300, 'platform_fee' => 15, 'total_amount' => 315, 'provider_earning' => 285]);
        $this->assertSame('payment_successful', $customer->notifications()->sole()->type);

        // The receipt link opens without a login token
        $this->get($response->json('data.receipt_url'))->assertOk()->assertSee($response->json('data.receipt_number'));
    }

    public function test_receipt_link_cannot_be_opened_without_signature(): void
    {
        $booking = Booking::factory()->create();

        $this->get("/receipts/{$booking->id}")->assertForbidden();
    }

    public function test_an_offer_on_a_cancelled_request_cannot_be_accepted(): void
    {
        $offer = Offer::factory()->for(ServiceRequest::factory()->cancelled())->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'card'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offer');

        $this->assertSame(0, Booking::count());
    }

    public function test_moyasar_payment_must_be_paid_with_the_offer_amount(): void
    {
        config(['services.payment.driver' => 'moyasar']);
        Http::fake([
            'api.moyasar.com/v1/payments/pay_wrong' => Http::response(['status' => 'paid', 'amount' => 100, 'currency' => 'SAR']),
            'api.moyasar.com/v1/payments/pay_ok' => Http::response(['status' => 'paid', 'amount' => 26250, 'currency' => 'SAR']),
        ]);
        $offer = Offer::factory()->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'card'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_id');

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'card', 'payment_id' => 'pay_wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_id');

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'apple_pay', 'payment_id' => 'pay_ok'])
            ->assertCreated();

        $this->assertDatabaseHas('payments', ['transaction_id' => 'pay_ok', 'gateway' => 'moyasar']);
    }

    public function test_fake_checkout_returns_the_success_link_and_the_offer_can_be_accepted_with_tabby(): void
    {
        $offer = Offer::factory()->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->getJson("/api/v1/offers/{$offer->id}")
            ->assertJsonPath('data.payment.methods.2.value', 'tabby')
            ->assertJsonPath('data.payment.methods.2.uses_checkout_page', true)
            ->assertJsonPath('data.payment.methods.3.value', 'tamara');

        $paymentId = $this->postJson("/api/v1/offers/{$offer->id}/checkout", ['payment_method' => 'tabby'])
            ->assertOk()
            ->assertJsonPath('data.amount', 262.5)
            ->json('data.payment_id');

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'tabby', 'payment_id' => $paymentId])
            ->assertCreated();

        $this->assertDatabaseHas('payments', ['transaction_id' => $paymentId, 'method' => 'tabby']);
    }

    public function test_card_cannot_be_used_for_the_checkout_page(): void
    {
        $offer = Offer::factory()->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/checkout", ['payment_method' => 'card'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_tabby_and_tamara_are_hidden_when_their_keys_are_missing(): void
    {
        config(['services.payment.driver' => 'live']);
        $offer = Offer::factory()->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->getJson("/api/v1/offers/{$offer->id}")->assertJsonCount(2, 'data.payment.methods');

        $this->postJson("/api/v1/offers/{$offer->id}/checkout", ['payment_method' => 'tamara'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_tabby_checkout_and_capture(): void
    {
        config(['services.payment.driver' => 'live', 'services.payment.tabby.secret_key' => 'sk_test']);
        $offer = Offer::factory()->create();
        $reference = 'OFFER-'.$offer->id;
        Http::fake([
            'api.tabby.ai/api/v2/checkout' => Http::response([
                'status' => 'created',
                'payment' => ['id' => 'tabby_pay_1'],
                'configuration' => ['available_products' => ['installments' => [['web_url' => 'https://checkout.tabby.ai/abc']]]],
            ]),
            'api.tabby.ai/api/v2/payments/tabby_pay_1' => Http::response([
                'id' => 'tabby_pay_1', 'status' => 'AUTHORIZED', 'amount' => '262.50', 'currency' => 'SAR', 'order' => ['reference_id' => $reference],
            ]),
            'api.tabby.ai/api/v2/payments/tabby_pay_1/captures' => Http::response(['status' => 'CLOSED']),
        ]);

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/checkout", ['payment_method' => 'tabby'])
            ->assertOk()
            ->assertJsonPath('data.payment_id', 'tabby_pay_1')
            ->assertJsonPath('data.checkout_url', 'https://checkout.tabby.ai/abc');

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'tabby', 'payment_id' => 'tabby_pay_1'])
            ->assertCreated();

        $this->assertDatabaseHas('payments', ['transaction_id' => 'tabby_pay_1', 'gateway' => 'tabby', 'method' => 'tabby']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/captures') && $request['amount'] === '262.50');
    }

    public function test_tabby_rejected_customer_gets_a_clear_message(): void
    {
        config(['services.payment.driver' => 'live', 'services.payment.tabby.secret_key' => 'sk_test']);
        Http::fake(['api.tabby.ai/*' => Http::response(['status' => 'rejected'])]);
        $offer = Offer::factory()->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/checkout", ['payment_method' => 'tabby'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment_method.0', __('messages.installment_not_available'));
    }

    public function test_tamara_payment_for_another_offer_is_refused(): void
    {
        config(['services.payment.driver' => 'live', 'services.payment.tamara.api_token' => 'token']);
        Http::fake([
            'api-sandbox.tamara.co/orders/tamara_order_1' => Http::response([
                'status' => 'approved', 'total_amount' => ['amount' => 250, 'currency' => 'SAR'], 'order_reference_id' => 'OFFER-999999',
            ]),
        ]);
        $offer = Offer::factory()->create();

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'tamara', 'payment_id' => 'tamara_order_1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_id');

        $this->assertSame(0, Booking::count());
    }

    public function test_tamara_payment_is_authorised_and_captured(): void
    {
        config(['services.payment.driver' => 'live', 'services.payment.tamara.api_token' => 'token']);
        $offer = Offer::factory()->create();
        Http::fake([
            'api-sandbox.tamara.co/orders/tamara_order_1' => Http::response([
                'status' => 'approved', 'total_amount' => ['amount' => 262.5, 'currency' => 'SAR'], 'order_reference_id' => 'OFFER-'.$offer->id,
            ]),
            'api-sandbox.tamara.co/orders/tamara_order_1/authorise' => Http::response(['status' => 'authorised']),
            'api-sandbox.tamara.co/payments/capture' => Http::response(['capture_id' => 'cap_1', 'status' => 'fully_captured']),
        ]);

        Sanctum::actingAs($offer->serviceRequest->customer);

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'tamara', 'payment_id' => 'tamara_order_1'])
            ->assertCreated();

        $this->assertDatabaseHas('payments', ['transaction_id' => 'tamara_order_1', 'gateway' => 'tamara']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/authorise'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments/capture'));
    }

    public function test_fee_percentages_come_from_settings(): void
    {
        Setting::create(['group' => 'fees', 'key' => 'customer_processing_fee_percent', 'value' => '10', 'type' => 'decimal']);
        Setting::create(['group' => 'fees', 'key' => 'provider_commission_percent', 'value' => '0', 'type' => 'decimal']);
        $offer = Offer::factory()->create(['price' => 200]);

        $this->assertSame([
            'service_price' => 200.0,
            'processing_fee_percent' => 10.0,
            'processing_fee' => 20.0,
            'total_amount' => 220.0,
            'provider_commission' => 0.0,
            'provider_earning' => 200.0,
        ], $offer->priceBreakdown());
    }

    public function test_guest_cannot_accept_an_offer(): void
    {
        $offer = Offer::factory()->create();

        Sanctum::actingAs(User::factory()->create(['is_guest' => true]));

        $this->postJson("/api/v1/offers/{$offer->id}/accept", ['payment_method' => 'card'])->assertForbidden();
    }

    public function test_customer_can_open_their_booking(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs($booking->customer);
        $this->getJson("/api/v1/bookings/{$booking->id}")->assertOk()->assertJsonPath('data.reference', $booking->reference);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/bookings/{$booking->id}")->assertNotFound();
    }
}
