<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reconciliation>
 */
class ReconciliationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'account_id' => Account::factory(),
            'statement_date' => fake()->date(),
            'statement_balance' => fake()->numberBetween(0, 500_000),
            'calculated_balance' => fake()->numberBetween(0, 500_000),
            'status' => 'matched',
            'discrepancy_amount' => 0,
            'notes' => null,
            'reconciled_by_user_id' => User::factory(),
            'reconciled_at' => now(),
        ];
    }
}
