<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Scenario;
use App\Models\User;
use App\Services\NetWorth\ScenarioService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scenario>
 */
class ScenarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->words(2, true),
            'base_year' => 2026,
            'horizon_years' => 30,
            'starting_investable_cents' => 5000000,
            'annual_contribution_cents' => 1200000,
            'contribution_years' => 20,
            'return_bps' => 500,
            'inflation_bps' => 200,
            'windfall_cents' => 0,
            'windfall_year' => null,
            'drawdown_start_year' => null,
            'drawdown_annual_cents' => 0,
            'downsizing_year' => null,
            'downsizing_proceeds_cents' => 0,
            'assumptions_snapshot' => ['inflation_rate' => 200],
            'formula_version' => ScenarioService::FORMULA_VERSION,
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
