<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedReport>
 */
class SavedReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->words(2, true),
            'type' => 'cash_flow',
            'filters' => ['from' => '2026-01-01', 'to' => '2026-12-31'],
            'created_by_user_id' => User::factory(),
        ];
    }
}
