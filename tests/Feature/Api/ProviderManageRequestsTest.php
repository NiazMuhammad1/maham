<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\District;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderManageRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_show_open_offer_sent_and_closed_requests(): void
    {
        $provider = $this->approvedProvider();

        $open = $this->matchingRequest($provider);
        $offerSent = $this->matchingRequest($provider);
        Offer::factory()->for($offerSent)->create(['provider_id' => $provider->id]);

        // Closed: another provider's offer was accepted, and an offer the provider withdrew
        $acceptedElsewhere = $this->matchingRequest($provider, ['status' => ServiceRequest::STATUS_ACCEPTED]);
        Offer::factory()->for($acceptedElsewhere)->create(['provider_id' => $provider->id, 'status' => Offer::STATUS_REJECTED]);
        $withdrawn = $this->matchingRequest($provider);
        Offer::factory()->for($withdrawn)->create(['provider_id' => $provider->id, 'status' => Offer::STATUS_WITHDRAWN]);

        // Not for this provider: another city
        ServiceRequest::factory()->for($provider->providedServices->first())->create();

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/provider/service-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.id', $open->id)
            ->assertJsonPath('data.requests.0.provider_status', 'open')
            ->assertJsonPath('data.requests.0.customer.id', $open->customer_id)
            ->assertJsonPath('data.requests.0.my_offer', null);

        $this->getJson('/api/v1/provider/service-requests?tab=offer_sent')
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.id', $offerSent->id)
            ->assertJsonPath('data.requests.0.provider_status_label', 'Offer Sent')
            ->assertJsonPath('data.requests.0.my_offer.status', Offer::STATUS_PENDING);

        $closedIds = collect($this->getJson('/api/v1/provider/service-requests?tab=closed')->json('data.requests'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$acceptedElsewhere->id, $withdrawn->id], $closedIds);

        // "All Services" filter
        $this->getJson('/api/v1/provider/service-requests?service_id='.Service::factory()->create()->id)
            ->assertJsonCount(0, 'data.requests');
    }

    public function test_provider_sends_an_offer_with_a_proposed_date(): void
    {
        $provider = $this->approvedProvider();
        $serviceRequest = $this->matchingRequest($provider);

        Sanctum::actingAs($provider);

        $this->getJson("/api/v1/provider/service-requests/{$serviceRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.can_send_offer', true);

        $date = now()->addDays(5)->toDateString();

        $this->postJson("/api/v1/provider/service-requests/{$serviceRequest->id}/offers", [
            'schedule_type' => 'proposed',
            'proposed_date' => $date,
            'proposed_time' => '14:00',
            'description' => 'I will check the compressor and refill the gas.',
            'price' => 300,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Offer Sent Successfully!')
            ->assertJsonPath('data.price', 300)
            ->assertJsonPath('data.date', $date)
            ->assertJsonPath('data.time', '14:00')
            ->assertJsonPath('data.can_withdraw', true)
            ->assertJsonPath('data.service_request.provider_status', 'offer_sent')
            ->assertJsonPath('data.earning.commission', 15)
            ->assertJsonPath('data.earning.provider_earning', 285);

        $this->assertSame(1, $serviceRequest->fresh()->offers_count);
        $this->assertSame('offer_received', $serviceRequest->customer->notifications()->value('type'));
        $this->assertSame('provider_offer_sent', $provider->notificationsFor(User::MODE_PROVIDER)->value('type'));

        // A second offer on the same request is not allowed
        $this->postJson("/api/v1/provider/service-requests/{$serviceRequest->id}/offers", [
            'schedule_type' => 'customer_time',
            'description' => 'Again',
            'price' => 200,
        ])->assertJsonValidationErrors('service_request');
    }

    public function test_offer_with_the_customers_time_ignores_proposed_fields(): void
    {
        $provider = $this->approvedProvider();
        $serviceRequest = $this->matchingRequest($provider);

        Sanctum::actingAs($provider);

        $this->postJson("/api/v1/provider/service-requests/{$serviceRequest->id}/offers", [
            'schedule_type' => 'customer_time',
            'proposed_date' => '2000-01-01',
            'description' => 'Fix the AC unit.',
            'price' => 250,
        ])
            ->assertCreated()
            ->assertJsonPath('data.schedule_type', 'customer_time')
            ->assertJsonPath('data.date', $serviceRequest->preferred_date->toDateString());
    }

    public function test_send_offer_validation_is_translated(): void
    {
        $provider = $this->approvedProvider();
        $serviceRequest = $this->matchingRequest($provider);

        Sanctum::actingAs($provider);

        $this->postJson("/api/v1/provider/service-requests/{$serviceRequest->id}/offers", ['schedule_type' => 'proposed'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proposed_date', 'proposed_time', 'description', 'price'])
            ->assertJsonPath('errors.price.0', 'حقل السعر المقترح مطلوب.');
    }

    public function test_provider_withdraws_a_pending_offer(): void
    {
        $provider = $this->approvedProvider();
        $serviceRequest = $this->matchingRequest($provider);
        $offer = Offer::factory()->for($serviceRequest)->create(['provider_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $this->getJson("/api/v1/provider/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.service_request.customer.id', $serviceRequest->customer_id);

        $this->postJson("/api/v1/provider/offers/{$offer->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('message', 'Your offer has been withdrawn.')
            ->assertJsonPath('data.status', Offer::STATUS_WITHDRAWN)
            ->assertJsonPath('data.can_withdraw', false)
            ->assertJsonPath('data.service_request.provider_status', 'closed');

        $this->assertSame(0, $serviceRequest->fresh()->offers_count);

        $this->postJson("/api/v1/provider/offers/{$offer->id}/withdraw")->assertJsonValidationErrors('offer');
    }

    public function test_provider_can_chat_with_the_customer_before_a_booking(): void
    {
        $provider = $this->approvedProvider();
        $serviceRequest = $this->matchingRequest($provider);

        Sanctum::actingAs($provider);

        $this->postJson("/api/v1/provider/service-requests/{$serviceRequest->id}/conversation")
            ->assertOk()
            ->assertJsonPath('data.conversation.call_phone', null);

        $this->assertTrue(Conversation::where('customer_id', $serviceRequest->customer_id)->where('provider_id', $provider->id)->exists());
    }

    public function test_providers_cannot_see_requests_outside_their_services_or_other_providers_offers(): void
    {
        $provider = $this->approvedProvider();
        $otherRequest = ServiceRequest::factory()->create();
        $otherOffer = Offer::factory()->create();

        Sanctum::actingAs($provider);

        $this->getJson("/api/v1/provider/service-requests/{$otherRequest->id}")->assertNotFound();
        $this->postJson("/api/v1/provider/service-requests/{$otherRequest->id}/conversation")->assertNotFound();
        $this->getJson("/api/v1/provider/offers/{$otherOffer->id}")->assertNotFound();
        $this->postJson("/api/v1/provider/offers/{$otherOffer->id}/withdraw")->assertNotFound();

        // Not an approved provider
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/provider/service-requests')->assertForbidden();
    }

    /**
     * An approved provider with one service and a whole-city service area.
     */
    private function approvedProvider(): User
    {
        $profile = ProviderProfile::factory()->create();
        $provider = $profile->user;

        $provider->providedServices()->attach(Service::factory()->create());
        $provider->serviceAreas()->create(['city_id' => District::factory()->create()->city_id]);

        return $provider->fresh();
    }

    /**
     * An open request in the provider's service and city.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function matchingRequest(User $provider, array $attributes = []): ServiceRequest
    {
        return ServiceRequest::factory()->for($provider->providedServices->first())->create([
            'city_id' => $provider->serviceAreas->first()->city_id,
            'preferred_date' => now()->addDays(2)->toDateString(),
            ...$attributes,
        ]);
    }
}
