<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_details_show_services_rating_bars_and_latest_reviews(): void
    {
        $profile = ProviderProfile::factory()->create();
        $provider = $profile->user;
        $provider->providedServices()->attach(Service::factory()->count(2)->create());

        foreach ([5, 5, 4, 2] as $rating) {
            $this->reviewFor($provider, $rating);
        }
        $profile->refreshRatingStats();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/providers/{$provider->id}")
            ->assertOk()
            ->assertJsonPath('data.reviews_count', 4)
            ->assertJsonPath('data.rating_avg', 4)
            ->assertJsonCount(2, 'data.services')
            ->assertJsonPath('data.rating_breakdown.0', ['stars' => 5, 'count' => 2])
            ->assertJsonPath('data.rating_breakdown.3', ['stars' => 2, 'count' => 1])
            ->assertJsonCount(3, 'data.latest_reviews');
    }

    public function test_customers_reviews_are_paginated_and_hide_hidden_reviews(): void
    {
        $provider = ProviderProfile::factory()->create()->user;
        $this->reviewFor($provider, 5);
        $this->reviewFor($provider, 1)->update(['is_visible' => false]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/providers/{$provider->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.total', 1);
    }

    public function test_customers_and_pending_providers_have_no_provider_profile(): void
    {
        $pendingProvider = ProviderProfile::factory()->pending()->create()->user;

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/providers/'.User::factory()->create()->id)->assertNotFound();
        $this->getJson("/api/v1/providers/{$pendingProvider->id}")->assertNotFound();
    }

    private function reviewFor(User $provider, int $rating): Review
    {
        $booking = Booking::factory()->completed()->create([
            'offer_id' => Offer::factory()->create(['provider_id' => $provider->id, 'status' => Offer::STATUS_ACCEPTED])->id,
        ]);

        return Review::factory()->for($booking)->create(['rating' => $rating]);
    }
}
