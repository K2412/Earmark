<?php

namespace App\Services\Transaction;

use App\Models\Bucket;
use App\Models\Transaction;
use App\Models\User;

class TransactionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Transaction
    {
        if (($data['amount'] ?? 0) > 0 && empty($data['bucket_id'])) {
            $data['bucket_id'] = Bucket::query()
                ->where('name', Bucket::UNASSIGNED_FUNDS)
                ->sole()
                ->id;
        }

        $data['created_by_user_id'] = $user->id;
        $data['cleared'] = (bool) ($data['cleared'] ?? false);

        return Transaction::query()->create($data);
    }
}
