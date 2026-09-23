<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionActivityLogger;
use App\Support\Money;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes a ledger transaction and records the deletion in the activity trail.
 * The audit entry keeps a description of what was removed even though the row is
 * gone (task #1246).
 */
class DeleteTransaction
{
    use AsAction;

    public function __construct(private TransactionActivityLogger $activity) {}

    public function handle(Transaction $transaction, User $user): void
    {
        $household = $transaction->household;

        $description = sprintf(
            "Deleted '%s' for %s on %s",
            $transaction->payee,
            Money::format($transaction->amount),
            $transaction->date->toDateString(),
        );

        $transaction->delete();

        $this->activity->log($household, $user, null, 'deleted', $description);
    }
}
