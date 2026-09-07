<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\HouseholdPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdPlan>
 */
class HouseholdPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'target_cents' => 1_000_000_000,
            'target_year' => 2056,
            'inflation_bps' => 200,
            'return_bps' => 700,
            'windfall_cents' => 0,
            'windfall_year' => null,
        ];
    }
}
