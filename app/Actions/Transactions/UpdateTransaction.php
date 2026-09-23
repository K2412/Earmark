<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionActivityLogger;
use App\Support\Money;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Edits a ledger transaction and records who changed what. Split transactions
 * keep their amount and attribution (those live on the splits); everything else
 * is editable. Balances and budgets are derived on read, so no stored total
 * needs recalculating (task #1246).
 */
class UpdateTransaction
{
    use AsAction;

    public function __construct(private TransactionActivityLogger $activity) {}

    /**
     * @param  array{date: string, account_id: string, payee: string, category_id: ?string, bucket_id: ?string, amount: int, memo: ?string, cleared: bool, reviewed: bool}  $data
     */
    public function handle(Transaction $transaction, array $data, User $user): Transaction
    {
        $original = $transaction->only([
            'date', 'account_id', 'payee', 'category_id', 'bucket_id', 'amount', 'memo', 'cleared', 'reviewed',
        ]);

        if ($transaction->is_split) {
            unset($data['amount'], $data['category_id'], $data['bucket_id']);
        }

        $transaction->fill($data)->save();

        $this->activity->log(
            $transaction->household,
            $user,
            $transaction,
            'updated',
            $this->describe($transaction, $original),
        );

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $original
     */
    private function describe(Transaction $transaction, array $original): string
    {
        $labels = [
            'date' => 'date',
            'account_id' => 'account',
            'payee' => 'payee',
            'category_id' => 'category',
            'bucket_id' => 'bucket',
            'amount' => 'amount',
            'memo' => 'memo',
            'cleared' => 'cleared',
            'reviewed' => 'reviewed',
        ];

        $changed = [];

        foreach ($labels as $field => $label) {
            if ((string) $transaction->getAttribute($field) !== (string) ($original[$field] ?? null)) {
                $changed[] = $label;
            }
        }

        if ($changed === []) {
            return sprintf("Edited '%s' (%s)", $transaction->payee, Money::format($transaction->amount));
        }

        return sprintf("Edited '%s' — changed %s", $transaction->payee, implode(', ', $changed));
    }
}
