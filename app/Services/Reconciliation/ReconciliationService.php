<?php

namespace App\Services\Reconciliation;

use App\Models\Account;
use App\Models\Transaction;

/**
 * Computes an account's cleared balance as of a statement date. The calculated
 * balance is the account's starting balance plus every cleared transaction up to
 * and including that date — the figure a bank statement should match (task #1251).
 */
class ReconciliationService
{
    public function calculatedBalance(Account $account, string $statementDate): int
    {
        $cleared = (int) Transaction::query()
            ->where('account_id', $account->id)
            ->where('cleared', true)
            ->whereDate('date', '<=', $statementDate)
            ->sum('amount');

        return $account->starting_balance + $cleared;
    }

    public function discrepancy(Account $account, string $statementDate, int $statementBalance): int
    {
        return $statementBalance - $this->calculatedBalance($account, $statementDate);
    }
}
