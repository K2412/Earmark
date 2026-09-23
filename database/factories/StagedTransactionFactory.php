<?php

namespace Database\Factories;

use App\Models\StagedTransaction;
use App\Models\StatementUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StagedTransaction>
 */
class StagedTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payee = fake()->company();

        return [
            'statement_upload_id' => StatementUpload::factory(),
            'date' => fake()->date(),
            'payee' => $payee,
            'raw_payee' => $payee,
            'row_fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'amount' => fake()->numberBetween(-50_000, 50_000) ?: -1000,
            'suggested_category_id' => null,
            'suggested_bucket_id' => null,
            'final_category_id' => null,
            'final_bucket_id' => null,
            'accept' => true,
            'is_possible_duplicate' => false,
            'duplicate_reason' => null,
            'duplicate_of_transaction_id' => null,
            'is_split' => false,
            'transaction_id' => null,
        ];
    }
}
