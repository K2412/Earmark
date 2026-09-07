<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['income', 'housing', 'transportation', 'food', 'household', 'personal', 'other']),
            'sort_order' => 0,
            'archived' => false,
            'notes' => null,
        ];
    }
}
