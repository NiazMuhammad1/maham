<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 */
class ProviderProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['is_provider' => true]),
            'business_name' => fake()->company(),
            'tagline' => 'Professional AC and Electronic Services',
            'skills_experience' => fake()->paragraph(),
            'status' => ProviderProfile::STATUS_APPROVED,
            'approved_at' => now(),
            'rating_avg' => 0,
            'reviews_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending', 'approved_at' => null]);
    }
}
