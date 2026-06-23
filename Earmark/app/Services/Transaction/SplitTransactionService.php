<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SplitTransactionService
{
    /**
     * @param  array<int, array{category_id?: string|null, bucket_id: string, amount: int, memo?: string|null}>  $splits
     */
    public function split(Transaction $transaction, array $splits): Transaction
    {
        return DB::transaction(function () use ($transaction, $splits): Transaction {
            $transaction->splits()->delete();

            foreach ($splits as $split) {
                $transaction->splits()->create($split);
            }

            $transaction->update(['is_split' => true]);

            return $transaction;
        });
    }
}
