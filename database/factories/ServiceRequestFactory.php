<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'service_id' => Service::factory(),
            'description' => fake()->sentence(12),
            'budget' => fake()->numberBetween(100, 900),
            'currency' => 'SAR',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'preferred_time' => '09:00',
            'flexibility' => 'flexible',
            'city_id' => City::factory(),
            'district_id' => null,
            'street_address' => fake()->streetAddress(),
            'postal_code' => '12345',
            'status' => ServiceRequest::STATUS_OPEN,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
