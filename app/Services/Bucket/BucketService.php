<?php

namespace App\Services\Bucket;

use App\Models\Bucket;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BucketService
{
    /**
     * @return Collection<int, Bucket>
     */
    public function listForHousehold(Household $household): Collection
    {
        return $household->buckets()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array{name: string, kind: string, monthly_obligation: int, target_amount?: int|null, target_date: string}  $data
     */
    public function create(array $data, User $user, Household $household): Bucket
    {
        return DB::transaction(function () use ($data, $user, $household): Bucket {
            $bucket = Bucket::query()->create([
                ...$data,
                'household_id' => $household->id,
            ]);

            $bucket->obligationVersions()->create([
                'monthly_obligation' => $bucket->monthly_obligation,
                'target_amount' => $bucket->target_amount,
                'target_date' => $bucket->target_date,
                'effective_year' => now()->year,
                'effective_month' => now()->month,
                'created_by_user_id' => $user->id,
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
