<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state. Customer and provider are copied from the booking.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->completed(),
            'customer_id' => fn (array $attributes) => Booking::find($attributes['booking_id'])->customer_id,
            'provider_id' => fn (array $attributes) => Booking::find($attributes['booking_id'])->provider_id,
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->sentence(20),
            'is_visible' => true,
        ];
    }
}
