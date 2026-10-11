<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'name_en' => fake()->unique()->streetName(),
            'name_ar' => 'حي '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
