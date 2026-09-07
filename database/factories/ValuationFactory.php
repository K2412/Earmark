<?php

namespace Database\Factories;

use App\Models\FinancialPosition;
use App\Models\User;
use App\Models\Valuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Valuation>
 */
class ValuationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_position_id' => FinancialPosition::factory(),
            'amount' => fake()->numberBetween(10_000, 5_000_000),
            'valued_at' => fake()->date(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
