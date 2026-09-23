<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\RecurringSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringSchedule>
 */
class RecurringScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->company(),
            'amount' => -fake()->numberBetween(500, 20000),
            'frequency' => 'monthly',
            'next_due_date' => now()->addWeeks(2)->toDateString(),
            'status' => 'confirmed',
            'detected' => false,
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
