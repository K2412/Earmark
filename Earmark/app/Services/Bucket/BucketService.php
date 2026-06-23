<?php

namespace App\Services\Bucket;

use App\Models\Bucket;
use Illuminate\Support\Facades\DB;

class BucketService
{
    /**
     * @param  array{name: string, kind: string, monthly_obligation: int, target_amount?: int|null, target_date: string}  $data
     */
    public function create(array $data, int $userId): Bucket
    {
        return DB::transaction(function () use ($data, $userId): Bucket {
            $bucket = Bucket::query()->create($data);

            $bucket->obligationVersions()->create([
                'monthly_obligation' => $bucket->monthly_obligation,
                'target_amount' => $bucket->target_amount,
                'target_date' => $bucket->target_date,
                'effective_year' => now()->year,
                'effective_month' => now()->month,
                'created_by_user_id' => $userId,
            ]);

            return $bucket;
        });
    }

    public function archive(Bucket $bucket): bool
    {
        if ($bucket->kind === 'system') {
            return false;
        }

        if ($bucket->transactions()->sum('amount') < 0) {
            return false;
        }

        return $bucket->update([
            'archived' => true,
            'archived_at' => now(),
        ]);
    }
}
