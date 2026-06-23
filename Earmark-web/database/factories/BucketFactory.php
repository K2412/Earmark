<?php

namespace Database\Factories;

use App\Models\Bucket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bucket>
 */
class BucketFactory extends Factory
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
            'kind' => 'ongoing',
            'monthly_obligation' => fake()->numberBetween(0, 50_000),
            'target_amount' => null,
            'target_date' => '9999-12-31',
            'archived' => false,
            'archived_at' => null,
            'sort_order' => 0,
            'notes' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'system',
            'monthly_obligation' => 0,
            'target_amount' => null,
            'target_date' => '9999-12-31',
        ]);
    }
}
