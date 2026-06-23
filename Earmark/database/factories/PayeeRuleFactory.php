<?php

namespace Database\Factories;

use App\Models\Category;
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
            'pattern' => fake()->company(),
            'category_id' => Category::factory(),
            'bucket_id' => null,
            'priority' => 100,
            'auto_apply' => true,
        ];
    }
}
