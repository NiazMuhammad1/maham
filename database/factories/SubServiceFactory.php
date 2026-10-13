<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\SubService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubService>
 */
class SubServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name_en' => ucwords(fake()->words(2, true)),
            'name_ar' => 'خدمة فرعية '.fake()->numberBetween(1, 999),
            'description_en' => fake()->sentence(),
            'description_ar' => 'وصف '.fake()->numberBetween(1, 999),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
