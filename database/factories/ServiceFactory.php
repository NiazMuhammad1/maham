<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name_en' => ucwords($name),
            'name_ar' => 'خدمة '.fake()->numberBetween(1, 9999),
            'description_en' => fake()->sentence(),
            'description_ar' => 'وصف '.fake()->numberBetween(1, 999),
            'icon' => 'services/'.Str::slug($name).'.png',
            'icon_bg_color' => '#B8EBD0',
            'image' => null,
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => ['is_popular' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
