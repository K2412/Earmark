<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'account_id' => Account::factory(),
            'payee' => fake()->company(),
            'category_id' => Category::factory(),
            'bucket_id' => Bucket::factory(),
            'amount' => fake()->numberBetween(-50_000, 50_000) ?: -1000,
            'memo' => null,
            'is_split' => false,
            'cleared' => false,
            'reconciled' => false,
            'source' => 'manual',
            'created_by_user_id' => User::factory(),
        ];
    }
}
