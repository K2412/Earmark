<?php

namespace Database\Factories;

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Models\FinancialPosition;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialPosition>
 */
class FinancialPositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'owner_user_id' => null,
            'name' => fake()->words(2, true),
            'classification' => PositionClassification::Asset,
            'purpose' => PositionPurpose::Investable,
            'archived' => false,
            'sort_order' => 0,
        ];
    }
}
