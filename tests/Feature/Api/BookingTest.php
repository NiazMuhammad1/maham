<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_bookings_shows_only_the_selected_tab(): void
    {
        $confirmed = Booking::factory()->create();
        $customer = $confirmed->customer;
        Booking::factory()->inProgress()->create(['customer_id' => $customer->id]);
        Booking::factory()->create(); // someone else's booking

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data.bookings')
            ->assertJsonPath('data.bookings.0.id', $confirmed->id)
            ->assertJsonPath('data.bookings.0.status_label', 'Confirmed');

        $this->getJson('/api/v1/bookings?tab=in_progress')
            ->assertOk()
            ->assertJsonCount(1, 'data.bookings')
            ->assertJsonPath('data.bookings.0.status', Booking::STATUS_IN_PROGRESS);

        $this->getJson('/api/v1/bookings?tab=completed')->assertOk()->assertJsonCount(0, 'data.bookings');
        $this->getJson('/api/v1/bookings?tab=wrong')->assertUnprocessable();
    }

    public function test_booking_details_show_the_timeline(): void
    {
        $booking = Booking::factory()->inProgress()->create();

        Sanctum::actingAs($booking->customer);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.timeline.0.key', 'confirmed')
            ->assertJsonPath('data.timeline.0.is_done', true)
            ->assertJsonPath('data.timeline.1.key', 'in_progress')
            ->assertJsonPath('data.timeline.1.is_current', true)
            ->assertJsonPath('data.timeline.2.is_done', false)
            ->assertJsonPath('data.timeline.2.message', null)
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.review', null);
    }

    public function test_customer_cannot_see_someone_elses_booking(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertNotFound();
        $this->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 5])->assertNotFound();
    }

    public function test_customer_rates_the_provider_after_the_work_is_completed(): void
    {
        $booking = Booking::factory()->completed()->create();

        Sanctum::actingAs($booking->customer);

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertJsonPath('data.can_review', true);

        $this->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 4, 'comment' => 'On time and clean work.'])
            ->assertCreated()
            ->assertJsonPath('message', 'Your review submitted successfully.')
            ->assertJsonPath('data.rating', 4);

        $this->assertSame(4.0, (float) $booking->provider->providerProfile->fresh()->rating_avg);
        $this->assertSame(1, $booking->provider->providerProfile->fresh()->reviews_count);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.review.comment', 'On time and clean work.')
            ->assertJsonPath('data.timeline.2.message', 'Your task is finished!');

        // Only one review per booking
        $this->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 5])->assertUnprocessable();
        $this->assertSame(1, Review::count());
    }

    public function test_customer_cannot_rate_before_the_work_is_completed(): void
    {
        $booking = Booking::factory()->inProgress()->create();

        Sanctum::actingAs($booking->customer);

        $this->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking');

        $this->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 6])->assertJsonValidationErrors('rating');
    }
}
