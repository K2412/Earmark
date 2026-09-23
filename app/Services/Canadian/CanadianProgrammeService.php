<?php

namespace App\Services\Canadian;

use App\Models\Account;
use App\Models\RegisteredAccountEvent;

/**
 * Reads registered-programme events (never balances) to answer questions about a
 * single account: contributions in a year, HBP outstanding and required annual
 * repayment, and contribution room. Room is an estimate unless an authoritative
 * CRA override event supplies it. Each account belongs to one owner, so
 * individual contribution room is never combined across spouses (task #1257).
 */
class CanadianProgrammeService
{
    public const HBP_REPAYMENT_YEARS = 15;

    public function contributions(Account $account, int $year): int
    {
        return (int) RegisteredAccountEvent::query()
            ->where('account_id', $account->id)
            ->where('type', 'contribution')
            ->where('plan_year', $year)
            ->sum('amount');
    }

    public function hbpWithdrawn(Account $account): int
    {
        return (int) RegisteredAccountEvent::query()
            ->where('account_id', $account->id)
            ->where('type', 'hbp_withdrawal')
            ->sum('amount');
    }

    public function hbpRepaid(Account $account): int
    {
        return (int) RegisteredAccountEvent::query()
            ->where('account_id', $account->id)
            ->where('type', 'hbp_repayment')
            ->sum('amount');
    }

    public function hbpOutstanding(Account $account): int
    {
        return $this->hbpWithdrawn($account) - $this->hbpRepaid($account);
    }

    public function hbpRequiredAnnualRepayment(Account $account): int
    {
        $withdrawn = $this->hbpWithdrawn($account);

        if ($withdrawn <= 0) {
            return 0;
        }

        return (int) ceil($withdrawn / self::HBP_REPAYMENT_YEARS);
    }

    public function roomOverride(Account $account, int $year): ?RegisteredAccountEvent
    {
        return RegisteredAccountEvent::query()
            ->where('account_id', $account->id)
            ->where('type', 'room_override')
            ->where('plan_year', $year)
            ->orderByDesc('as_of')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{amount: int, authoritative: bool, as_of: ?string}
     */
    public function room(Account $account, int $annualLimit, int $year): array
    {
        $override = $this->roomOverride($account, $year);

        if ($override !== null) {
            return [
                'amount' => $override->amount,
                'authoritative' => true,
                'as_of' => $override->as_of?->toDateString(),
            ];
        }

        return [
            'amount' => $annualLimit - $this->contributions($account, $year),
            'authoritative' => false,
            'as_of' => null,
        ];
    }
}
