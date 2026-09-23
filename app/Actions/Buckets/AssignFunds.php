<?php

namespace App\Actions\Buckets;

use App\Models\BucketAssignment;
use App\Models\Household;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves money between two buckets for a given month. Because the same amount
 * leaves the source and enters the destination, every cent is conserved — the
 * household's total available never changes from an assignment (task #1252).
 */
class AssignFunds
{
    use AsAction;

    /**
     * @param  array{from_bucket_id: string, to_bucket_id: string, amount: int, year: int, month: int, memo: ?string}  $data
     */
    public function handle(array $data, User $user, Household $household): BucketAssignment
    {
        return BucketAssignment::query()->create([
            'household_id' => $household->id,
            'from_bucket_id' => $data['from_bucket_id'],
            'to_bucket_id' => $data['to_bucket_id'],
            'year' => $data['year'],
            'month' => $data['month'],
            'amount' => $data['amount'],
            'memo' => $data['memo'] ?? null,
            'created_by_user_id' => $user->id,
        ]);
    }
}
