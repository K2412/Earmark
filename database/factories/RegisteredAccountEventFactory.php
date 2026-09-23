<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use App\Models\RegisteredAccountEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegisteredAccountEvent>
 */
class RegisteredAccountEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'account_id' => Account::factory(),
            'owner_user_id' => null,
            'type' => 'contribution',
            'amount' => fake()->numberBetween(1000, 500000),
            'occurred_on' => now()->toDateString(),
            'plan_year' => (int) now()->year,
            'as_of' => null,
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
