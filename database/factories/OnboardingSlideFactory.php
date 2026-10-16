<?php

namespace Database\Factories;

use App\Models\OnboardingSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingSlide>
 */
class OnboardingSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => 'customer',
            'title_en' => fake()->sentence(4),
            'title_ar' => 'عنوان '.fake()->numberBetween(1, 999),
            'description_en' => fake()->sentence(),
            'description_ar' => 'وصف '.fake()->numberBetween(1, 999),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
