<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\District;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_performance_upcoming_bookings_and_matching_requests(): void
    {
        $provider = $this->approvedProvider();
        $service = $provider->providedServices->first();
        $area = $provider->serviceAreas->first();

        // Earnings this month: 2 completed bookings (one of them rated), 1 from last month
        $first = $this->bookingFor($provider, ['status' => Booking::STATUS_COMPLETED, 'completed_at' => now(), 'provider_earning' => 200]);
        $this->bookingFor($provider, ['status' => Booking::STATUS_COMPLETED, 'completed_at' => now(), 'provider_earning' => 65]);
        $this->bookingFor($provider, ['status' => Booking::STATUS_COMPLETED, 'completed_at' => now()->subMonthNoOverflow(), 'provider_earning' => 999]);
        Review::factory()->for($first)->create(['rating' => 5]);

        // Upcoming: only confirmed bookings from today on
        $upcoming = $this->bookingFor($provider, ['scheduled_date' => now()->addDay()->toDateString()]);

        // Recent requests: only open requests in the provider's service and city
        $matching = ServiceRequest::factory()->for($service)->create(['city_id' => $area->city_id, 'preferred_date' => now()->addDays(2)]);
        ServiceRequest::factory()->for(Service::factory()->create())->create(['city_id' => $area->city_id]);
        ServiceRequest::factory()->for($service)->create(['preferred_date' => now()->addDays(2)]); // other city

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/provider/dashboard')
            ->assertOk()
            ->assertJsonPath('data.provider.id', $provider->id)
            ->assertJsonPath('data.performance.earnings', 265)
            ->assertJsonPath('data.performance.completed_bookings', 2)
            ->assertJsonPath('data.performance.rating_avg', 5)
            ->assertJsonCount(12, 'data.months')
            ->assertJsonCount(1, 'data.upcoming_bookings')
            ->assertJsonPath('data.upcoming_bookings.0.id', $upcoming->id)
            ->assertJsonCount(1, 'data.recent_requests')
            ->assertJsonPath('data.recent_requests.0.id', $matching->id);

        $this->getJson('/api/v1/provider/dashboard?month='.now()->subMonthNoOverflow()->format('Y-m'))
            ->assertJsonPath('data.performance.earnings', 999)
            ->assertJsonPath('data.performance.rating_avg', null);
    }

    public function test_requests_the_provider_already_offered_on_are_not_recent_requests(): void
    {
        $provider = $this->approvedProvider();
        $serviceRequest = ServiceRequest::factory()->for($provider->providedServices->first())->create([
            'city_id' => $provider->serviceAreas->first()->city_id,
            'preferred_date' => now()->addDays(2),
        ]);
        Offer::factory()->for($serviceRequest)->create(['provider_id' => $provider->id]);

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/provider/dashboard')->assertJsonCount(0, 'data.recent_requests');
    }

    public function test_earnings_are_filtered_by_period(): void
    {
        $provider = $this->approvedProvider();
        $this->bookingFor($provider, ['status' => Booking::STATUS_COMPLETED, 'completed_at' => now(), 'provider_earning' => 250]);
        $this->bookingFor($provider, ['status' => Booking::STATUS_COMPLETED, 'completed_at' => now()->subYears(2), 'provider_earning' => 100]);
        $this->bookingFor($provider); // confirmed, not earned yet

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/provider/earnings')
            ->assertOk()
            ->assertJsonPath('data.period', 'weekly')
            ->assertJsonPath('data.earnings', 250)
            ->assertJsonPath('data.completed_bookings', 1)
            ->assertJsonCount(5, 'data.periods')
            ->assertJsonPath('data.bookings.0.provider_earning', 250);

        $this->getJson('/api/v1/provider/earnings?period=all')
            ->assertJsonPath('data.earnings', 350)
            ->assertJsonPath('data.completed_bookings', 2);
    }

    public function test_provider_screens_are_only_for_approved_providers(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/provider/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'This section is available for approved service providers only.');
        $this->getJson('/api/v1/provider/earnings')->assertForbidden();

        $this->putJson('/api/v1/profile/mode', ['mode' => 'provider'])->assertJsonValidationErrors('mode');
    }

    public function test_approved_provider_switches_mode(): void
    {
        $provider = $this->approvedProvider();

        Sanctum::actingAs($provider);

        $this->putJson('/api/v1/profile/mode', ['mode' => 'provider'])
            ->assertOk()
            ->assertJsonPath('message', 'Switched to Provider Mode.')
            ->assertJsonPath('data.user.active_mode', 'provider');

        $this->putJson('/api/v1/profile/mode', ['mode' => 'customer'])
            ->assertJsonPath('data.user.active_mode', 'customer');
    }

    public function test_customer_and_provider_notifications_are_separate_lists(): void
    {
        $provider = $this->approvedProvider();
        $provider->addAppNotification('work_started', ['provider' => 'Khalid']);
        $provider->addProviderNotification('provider_work_started', [
            'customer' => 'Abdul Rehman',
            'service' => ['en' => 'AC Repair', 'ar' => 'إصلاح المكيفات'],
        ]);

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/notifications')
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.type', 'work_started');

        $this->getJson('/api/v1/notifications?mode=provider')
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.body', 'You have started working on the AC Repair request for Abdul Rehman.');

        // The service name is shown in the reader's language
        $this->getJson('/api/v1/notifications?mode=provider', ['Accept-Language' => 'ar'])
            ->assertJsonPath('data.notifications.0.body', 'لقد بدأت العمل على طلب إصلاح المكيفات الخاص بـ Abdul Rehman.');

        $this->postJson('/api/v1/notifications/read-all?mode=provider')->assertOk();
        $this->assertSame(1, $provider->notificationsFor(User::MODE_CUSTOMER)->whereNull('read_at')->count());
    }

    public function test_provider_is_notified_when_work_starts_and_when_the_customer_rates(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs($booking->provider);
        $this->postJson("/api/v1/provider/bookings/{$booking->id}/start")->assertOk();
        $this->postJson("/api/v1/provider/bookings/{$booking->id}/complete")->assertOk();

        Sanctum::actingAs($booking->customer);
        $this->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 5])->assertCreated();

        $types = $booking->provider->notificationsFor(User::MODE_PROVIDER)->oldest()->pluck('type')->all();
        $this->assertSame(['provider_work_started', 'provider_work_rated'], $types);
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
     * @param  array<string, mixed>  $attributes
     */
    private function bookingFor(User $provider, array $attributes = []): Booking
    {
        $offer = Offer::factory()->create(['provider_id' => $provider->id, 'status' => Offer::STATUS_ACCEPTED]);

        return Booking::factory()->create(['offer_id' => $offer->id, ...$attributes]);
    }
}
