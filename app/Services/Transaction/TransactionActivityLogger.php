<?php

namespace App\Services\Transaction;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\TransactionActivity;
use App\Models\User;

/**
 * Records a human-readable, household-scoped audit entry for a ledger change.
 * Entries survive deletion of the underlying transaction (task #1246).
 */
class TransactionActivityLogger
{
    public function log(Household $household, User $user, ?Transaction $transaction, string $action, string $description): TransactionActivity
    {
        return TransactionActivity::query()->create([
            'household_id' => $household->id,
            'transaction_id' => $transaction?->id,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
