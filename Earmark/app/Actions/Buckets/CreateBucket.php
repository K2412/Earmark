<?php

namespace App\Actions\Buckets;

use App\Models\Bucket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBucket
{
    use AsAction;

    /**
     * Creates a bucket plus its first BucketObligationVersion record so the
     * obligation history is captured from month 1.
     *
     * @param  array{name: string, kind: string, monthly_obligation: int, target_amount?: int|null, target_date: string}  $data
     */
    public function handle(array $data, User $user): Bucket
    {
        return DB::transaction(function () use ($data, $user) {
            $bucket = Bucket::query()->create($data);

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
}
