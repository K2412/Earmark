<?php

namespace Database\Factories;

use App\Models\Assumption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assumption>
 */
class AssumptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => null,
            'key' => 'tfsa_limit',
            'jurisdiction' => 'CA',
            'value' => 700000,
            'unit' => 'cents',
            'effective_year' => 2026,
            'source_url' => 'https://example.test/source',
            'source_date' => '2026-01-01',
            'catalogue_version' => '2026.1',
            'notes' => null,
            'created_by_user_id' => null,
        ];
    }
}
