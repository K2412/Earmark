<?php

namespace App\Actions\Buckets;

use App\Models\Bucket;
use App\Models\BucketObligationVersion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records an effective-dated obligation change for a bucket. Because it adds a
 * new version rather than editing the bucket, prior months keep the obligation
 * that applied then — changing a target never rewrites history (task #1252).
 */
class SetBucketObligation
{
    use AsAction;

    /**
     * @param  array{monthly_obligation: int, target_amount: ?int, target_date: ?string, effective_year: int, effective_month: int}  $data
     */
    public function handle(Bucket $bucket, array $data, User $user): BucketObligationVersion
    {
        return BucketObligationVersion::query()->create([
            'bucket_id' => $bucket->id,
            'monthly_obligation' => $data['monthly_obligation'],
            'target_amount' => $data['target_amount'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'effective_year' => $data['effective_year'],
            'effective_month' => $data['effective_month'],
            'created_by_user_id' => $user->id,
        ]);
    }
}
