<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['income', 'housing', 'transportation', 'food', 'household', 'personal', 'other']),
            'sort_order' => 0,
            'archived' => false,
            'notes' => null,
        ];
    }
}
