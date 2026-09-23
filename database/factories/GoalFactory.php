<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->words(2, true),
            'type' => 'save_up',
            'target_amount' => fake()->numberBetween(100000, 5000000),
            'target_date' => now()->addYear()->toDateString(),
            'apr_bps' => null,
            'required_payment' => null,
            'principal' => null,
            'linked_account_id' => null,
            'linked_bucket_id' => null,
            'linked_position_id' => null,
            'sort_order' => 0,
            'status' => 'active',
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
