<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionActivityLogger;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Splits a ledger transaction across buckets/categories, or clears the split when
 * given no allocations. The split children always sum to the parent amount
 * (validated in the request), preserving conservation (task #1251).
 */
class SplitTransaction
{
    use AsAction;

    public function __construct(private TransactionActivityLogger $activity) {}

    /**
     * @param  list<array{category_id: ?string, bucket_id: string, amount: int, memo: ?string}>  $splits
     */
    public function handle(Transaction $transaction, array $splits, User $user): Transaction
    {
        DB::transaction(function () use ($transaction, $splits): void {
            $transaction->splits()->delete();

            if ($splits === []) {
                $transaction->is_split = false;
                $transaction->save();

                return;
            }

            $transaction->is_split = true;
            $transaction->category_id = null;
            $transaction->bucket_id = null;
            $transaction->save();

            foreach ($splits as $split) {
                $transaction->splits()->create([
                    'category_id' => $split['category_id'] ?? null,
                    'bucket_id' => $split['bucket_id'],
                    'amount' => $split['amount'],
                    'memo' => $split['memo'] ?? null,
                ]);
            }
        });

        $this->activity->log(
            $transaction->household,
            $user,
            $transaction,
            'split',
            $splits === []
                ? sprintf("Removed split from '%s'", $transaction->payee)
                : sprintf("Split '%s' into %d allocation(s)", $transaction->payee, count($splits)),
        );

        return $transaction;
    }
}
