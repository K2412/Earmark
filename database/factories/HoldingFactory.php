<?php

namespace Database\Factories;

use App\Models\Holding;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holding>
 */
class HoldingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(100000, 5000000);

        return [
            'household_id' => Household::factory(),
            'account_id' => null,
            'name' => fake()->company(),
            'symbol' => strtoupper(fake()->lexify('???')),
            'asset_class' => 'equity',
            'cost_basis_cents' => $cost,
            'market_value_cents' => $cost + fake()->numberBetween(-50000, 200000),
            'target_allocation_bps' => null,
            'currency' => 'CAD',
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
