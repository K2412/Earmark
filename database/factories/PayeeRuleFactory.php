<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Household;
use App\Models\PayeeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayeeRule>
 */
class PayeeRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => null,
            'pattern' => fake()->company(),
            'enabled' => true,
            'category_id' => Category::factory(),
            'bucket_id' => null,
            'rename_to' => null,
            'hide_from_reports' => false,
            'mark_for_review' => false,
            'priority' => 100,
            'auto_apply' => true,
        ];
    }
}
