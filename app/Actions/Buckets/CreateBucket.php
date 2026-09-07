<?php

namespace App\Actions\Buckets;

use App\Models\Bucket;
use App\Models\Household;
use App\Models\User;
use App\Services\Bucket\BucketService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBucket
{
    use AsAction;

    public function __construct(private BucketService $buckets) {}

    /**
     * @param  array{name: string, kind: string, monthly_obligation: int, target_amount?: int|null, target_date: string}  $data
     */
    public function handle(array $data, User $user, Household $household): Bucket
    {
        return $this->buckets->create($data, $user, $household);
    }
}
