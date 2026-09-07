<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['chequing', 'savings', 'credit_card', 'cash', 'other']),
            'starting_balance' => fake()->numberBetween(0, 500_000),
            'starting_balance_date' => fake()->date(),
            'archived' => false,
            'sort_order' => 0,
        ];
    }
}
