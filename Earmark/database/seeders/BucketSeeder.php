<?php

namespace Database\Seeders;

use App\Models\Bucket;
use Illuminate\Database\Seeder;

class BucketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Bucket::query()->firstOrCreate(
            ['name' => Bucket::UNASSIGNED_FUNDS],
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
