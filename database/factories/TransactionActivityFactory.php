<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\TransactionActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionActivity>
 */
class TransactionActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'transaction_id' => Transaction::factory(),
            'user_id' => User::factory(),
            'action' => 'updated',
            'description' => fake()->sentence(),
        ];
    }
}
