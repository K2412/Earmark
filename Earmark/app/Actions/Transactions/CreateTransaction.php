<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTransaction
{
    use AsAction;

    /**
     * @param  array{date: string, account_id: string, payee: string, category_id: ?string, bucket_id: ?string, amount: int, memo: ?string}  $data
     */
    public function handle(array $data, User $user): Transaction
    {
        return Transaction::query()->create([
            ...$data,
            'source' => 'manual',
            'created_by_user_id' => $user->id,
        ]);
    }
}
