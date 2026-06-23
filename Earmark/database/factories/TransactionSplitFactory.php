<?php

namespace Database\Factories;

use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionSplit>
 */
class TransactionSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'category_id' => Category::factory(),
            'bucket_id' => Bucket::factory(),
            'amount' => fake()->numberBetween(-50_000, -100),
            'memo' => null,
        ];
    }
}
