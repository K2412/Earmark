<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use App\Models\StatementUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementUpload>
 */
class StatementUploadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'account_id' => Account::factory(),
            'source' => 'csv',
            'original_filename' => fake()->word().'.csv',
            'file_sha256' => hash('sha256', fake()->unique()->uuid()),
            'file_size_bytes' => fake()->numberBetween(100, 50_000),
            'status' => 'parsed',
            'parser_version' => 'earmark.csv.v1',
            'parsed_transaction_count' => 0,
            'imported_transaction_count' => 0,
            'error_message' => null,
            'uploaded_by_user_id' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
