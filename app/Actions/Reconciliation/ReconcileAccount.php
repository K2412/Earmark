<?php

namespace App\Actions\Reconciliation;

use App\Models\Account;
use App\Models\Reconciliation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Attempts to reconcile an account against a statement. It commits only when the
 * calculated cleared balance matches the statement balance exactly — a
 * discrepancy is reported and nothing is written (no automatic acceptance,
 * task #1251). On a match, cleared unreconciled rows up to the statement date are
 * marked reconciled and a Reconciliation record is stored.
 */
class ReconcileAccount
{
    use AsAction;

    public function __construct(private ReconciliationService $reconciliation) {}

    /**
     * @return array{matched: bool, calculated: int, discrepancy: int, reconciliation: ?Reconciliation}
     */
    public function handle(Account $account, string $statementDate, int $statementBalance, User $user): array
    {
        $calculated = $this->reconciliation->calculatedBalance($account, $statementDate);
        $discrepancy = $statementBalance - $calculated;

        if ($discrepancy !== 0) {
            return ['matched' => false, 'calculated' => $calculated, 'discrepancy' => $discrepancy, 'reconciliation' => null];
        }

        $reconciliation = DB::transaction(function () use ($account, $statementDate, $statementBalance, $calculated, $user): Reconciliation {
            Transaction::query()
                ->where('account_id', $account->id)
                ->where('cleared', true)
                ->where('reconciled', false)
                ->whereDate('date', '<=', $statementDate)
                ->update(['reconciled' => true]);

            return Reconciliation::query()->create([
                'household_id' => $account->household_id,
                'account_id' => $account->id,
                'statement_date' => $statementDate,
                'statement_balance' => $statementBalance,
                'calculated_balance' => $calculated,
                'status' => 'matched',
                'discrepancy_amount' => 0,
                'reconciled_by_user_id' => $user->id,
                'reconciled_at' => now(),
            ]);
        });

        return ['matched' => true, 'calculated' => $calculated, 'discrepancy' => 0, 'reconciliation' => $reconciliation];
    }
}
