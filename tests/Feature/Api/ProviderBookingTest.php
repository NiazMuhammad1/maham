<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_starts_a_confirmed_booking(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs($booking->provider);

        $this->postJson("/api/v1/provider/bookings/{$booking->id}/start")
            ->assertOk()
            ->assertJsonPath('message', 'Work started. The customer has been notified.')
            ->assertJsonPath('data.status', Booking::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.timeline.1.is_done', true)
            ->assertJsonPath('data.timeline.1.is_current', true);

        $this->assertNotNull($booking->fresh()->started_at);
        $this->assertSame('work_started', $booking->customer->notifications()->first()->type);
    }

    public function test_provider_completes_a_booking_in_progress(): void
    {
        $booking = Booking::factory()->inProgress()->create();

        Sanctum::actingAs($booking->provider);

        $this->postJson("/api/v1/provider/bookings/{$booking->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_COMPLETED)
            ->assertJsonPath('data.timeline.2.is_current', true)
            ->assertJsonPath('data.can_review', true);

        $this->assertNotNull($booking->fresh()->completed_at);
        $this->assertSame('work_completed', $booking->customer->notifications()->first()->type);
    }

    public function test_steps_cannot_be_skipped_or_repeated(): void
    {
        $confirmed = Booking::factory()->create();
        $completed = Booking::factory()->completed()->create(['provider_id' => $confirmed->provider_id]);

        Sanctum::actingAs($confirmed->provider);

        $this->postJson("/api/v1/provider/bookings/{$confirmed->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking');

        $this->postJson("/api/v1/provider/bookings/{$completed->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking');

        $this->assertSame(Booking::STATUS_CONFIRMED, $confirmed->fresh()->status);
    }

    public function test_only_the_bookings_provider_can_change_it(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs($booking->customer);
        $this->postJson("/api/v1/provider/bookings/{$booking->id}/start")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/provider/bookings/{$booking->id}/start")->assertNotFound();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }
}
