<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\ServiceRequest;
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
            ->assertJsonPath('data.payment.amount_halalas', 25000)
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
            ->assertJsonPath('data.total_amount', 300)
            ->assertJsonPath('data.payment_method', 'card')
            ->assertJsonPath('data.provider.id', $offer->provider_id);

        $this->assertSame(Offer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame(Offer::STATUS_REJECTED, $otherOffer->fresh()->status);
        $this->assertSame(ServiceRequest::STATUS_ACCEPTED, $offer->serviceRequest->fresh()->status);
        $this->assertSame($offer->id, $offer->serviceRequest->fresh()->accepted_offer_id);
        $this->assertDatabaseHas('payments', ['amount' => 300, 'status' => 'paid', 'method' => 'card']);
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
            'api.moyasar.com/v1/payments/pay_ok' => Http::response(['status' => 'paid', 'amount' => 25000, 'currency' => 'SAR']),
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
