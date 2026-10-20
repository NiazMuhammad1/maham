<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'provider_id' => fn () => ProviderProfile::factory()->create()->user_id,
            'price' => 250,
            'currency' => 'SAR',
            'description' => fake()->sentence(15),
            'schedule_type' => 'customer_time',
            'status' => Offer::STATUS_PENDING,
        ];
    }

    /**
     * Keep the request's "Offers (3)" counter in step with its offers.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Offer $offer) => $offer->serviceRequest->increment('offers_count'));
    }

    /**
     * The provider suggested another date and time.
     */
    public function proposed(string $date, string $time = '10:30'): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'proposed',
            'proposed_date' => $date,
            'proposed_time' => $time,
        ]);
    }
}
