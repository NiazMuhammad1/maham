<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state. Customer, provider, service and address are copied from the offer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory()->state(['status' => Offer::STATUS_ACCEPTED, 'accepted_at' => now()]),
            'service_request_id' => fn (array $attributes) => Offer::find($attributes['offer_id'])->service_request_id,
            'customer_id' => fn (array $attributes) => Offer::find($attributes['offer_id'])->serviceRequest->customer_id,
            'provider_id' => fn (array $attributes) => Offer::find($attributes['offer_id'])->provider_id,
            'service_id' => fn (array $attributes) => Offer::find($attributes['offer_id'])->serviceRequest->service_id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '09:00',
            'city_id' => fn (array $attributes) => Offer::find($attributes['offer_id'])->serviceRequest->city_id,
            'street_address' => fake()->streetAddress(),
            'service_price' => 250,
            'vat_amount' => 0,
            'platform_fee' => 0,
            'total_amount' => 250,
            'provider_earning' => 250,
            'currency' => 'SAR',
            'status' => Booking::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'confirmed_at' => now(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_COMPLETED,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }
}
