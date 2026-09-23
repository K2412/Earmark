<?php

namespace App\Services\Payee;

use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Suggests a category/bucket for an imported payee from the household's own history:
 * the most recent ledger transaction that was categorized for the same payee. This
 * complements explicit payee rules (#1270) — rules are the deliberate override, this
 * covers every payee the user categorized once by hand but never wrote a rule for.
 *
 * Matching is exact on the normalized payee (lowercased, trimmed). Fuzzy matching is
 * intentionally left to payee rules, which the user controls.
 */
class PayeeHistoryService
{
    /**
     * Build a lookup of the most recently used category/bucket for each given payee.
     * Only categorized transactions contribute; the `category_id` filter naturally
     * excludes split parents and transfers, which carry no category of their own.
     *
     * @param  list<string>  $payees
     * @return array<string, array{category_id: ?string, bucket_id: ?string}>
     */
    public function mapFor(array $payees, Household $household): array
    {
        $keys = collect($payees)
            ->map(fn (string $payee): string => $this->key($payee))
            ->filter(fn (string $key): bool => $key !== '')
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        $transactions = Transaction::query()
            ->where('household_id', $household->id)
            ->whereNotNull('category_id')
            ->whereIn(DB::raw('lower(trim(payee))'), $keys->all())
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get(['payee', 'category_id', 'bucket_id']);

        $map = [];

        foreach ($transactions as $transaction) {
            $key = $this->key($transaction->payee);

            // Ordered newest-first, so the first row seen for a payee is the latest.
            if (! isset($map[$key])) {
                $map[$key] = [
                    'category_id' => $transaction->category_id,
                    'bucket_id' => $transaction->bucket_id,
                ];
            }
        }

        return $map;
    }

    public function key(string $payee): string
    {
        return mb_strtolower(trim($payee));
    }
}
