<?php

namespace Database\Seeders;

use App\Models\Bucket;
use App\Models\Household;
use Illuminate\Database\Seeder;

class BucketSeeder extends Seeder
{
    public function run(?Household $household = null): void
    {
        $households = $household
            ? collect([$household])
            : Household::query()->get();

        foreach ($households as $target) {
            Bucket::query()->firstOrCreate(
                [
                    'household_id' => $target->id,
                    'name' => Bucket::UNASSIGNED_FUNDS,
                ],
                [
                    'kind' => 'system',
                    'monthly_obligation' => 0,
                    'target_amount' => null,
                    'target_date' => '9999-12-31',
                    'archived' => false,
                    'sort_order' => 0,
                ],
            );
        }
    }
}
