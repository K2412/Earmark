<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class EffectiveTransactionLineService
{
    /**
     * @return Collection<int, array{transaction_id: string, category_id: string|null, bucket_id: string|null, amount: int}>
     */
    public function linesFor(Transaction $transaction): Collection
    {
        if ($transaction->is_split) {
            return $transaction->splits()->get()->map(fn ($split): array => [
                'transaction_id' => $transaction->id,
                'category_id' => $split->category_id,
                'bucket_id' => $split->bucket_id,
                'amount' => $split->amount,
            ]);
        }

        return collect([
            [
                'transaction_id' => $transaction->id,
                'category_id' => $transaction->category_id,
                'bucket_id' => $transaction->bucket_id,
                'amount' => $transaction->amount,
            ],
        ]);
    }
}
