<?php

namespace App\Services\Transaction;

use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the filtered transaction-register query for a household. Transfers are
 * excluded — they are managed on their own page. All filtering is server-side;
 * Svelte never computes authoritative values (task #1246).
 */
class TransactionRegisterService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Transaction>
     */
    public function query(Household $household, array $filters): Builder
    {
        $query = Transaction::query()
            ->where('household_id', $household->id)
            ->with(['account', 'category', 'bucket'])
            ->whereNull('transfer_pair_id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['bucket_id'])) {
            $query->where('bucket_id', $filters['bucket_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['payee']) && $filters['payee'] !== '') {
            $query->where('payee', 'like', '%'.$filters['payee'].'%');
        }

        if (isset($filters['amount_min']) && $filters['amount_min'] !== null) {
            $query->where('amount', '>=', (int) $filters['amount_min']);
        }

        if (isset($filters['amount_max']) && $filters['amount_max'] !== null) {
            $query->where('amount', '<=', (int) $filters['amount_max']);
        }

        if (isset($filters['cleared']) && $filters['cleared'] !== null) {
            $query->where('cleared', (bool) $filters['cleared']);
        }

        if (isset($filters['reviewed']) && $filters['reviewed'] !== null) {
            $query->where('reviewed', (bool) $filters['reviewed']);
        }

        return $query
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');
    }
}
