<?php

namespace App\Services\Reporting;

use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Canonical, server-side reporting queries. Totals are computed from the same
 * filtered transaction rows the drilldown returns, so a report always reconciles
 * to its rows. Transfers and rows hidden by rules (excluded_from_reports) are
 * omitted (task #1255).
 */
class ReportingService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{income_cents: int, expense_cents: int, net_cents: int}
     */
    public function cashFlow(Household $household, array $filters): array
    {
        $amounts = $this->query($household, $filters)->pluck('amount');
        $income = (int) $amounts->filter(fn (int $a): bool => $a > 0)->sum();
        $expense = (int) $amounts->filter(fn (int $a): bool => $a < 0)->sum();

        return [
            'income_cents' => $income,
            'expense_cents' => $expense,
            'net_cents' => $income + $expense,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{category: string, total_cents: int}>
     */
    public function byCategory(Household $household, array $filters, string $direction): array
    {
        $query = $this->query($household, $filters)->with('category:id,name');
        $query = $direction === 'income'
            ? $query->where('amount', '>', 0)
            : $query->where('amount', '<', 0);

        return $query->get()
            ->groupBy(fn (Transaction $t): string => $t->category?->name ?? 'Uncategorized')
            ->map(fn ($group, string $name): array => [
                'category' => $name,
                'total_cents' => (int) $group->sum('amount'),
            ])
            ->sortBy('total_cents')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id: string, date: string, account: ?string, payee: string, category: ?string, amount_cents: int}>
     */
    public function drilldown(Household $household, array $filters, int $limit = 500): array
    {
        return $this->query($household, $filters)
            ->with(['account:id,name', 'category:id,name'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $t): array => [
                'id' => $t->id,
                'date' => $t->date->toDateString(),
                'account' => $t->account?->name,
                'payee' => $t->payee,
                'category' => $t->category?->name,
                'amount_cents' => $t->amount,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Transaction>
     */
    private function query(Household $household, array $filters): Builder
    {
        return Transaction::query()
            ->where('household_id', $household->id)
            ->whereNull('transfer_pair_id')
            ->where('excluded_from_reports', false)
            ->when(! empty($filters['from']), fn (Builder $q) => $q->whereDate('date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn (Builder $q) => $q->whereDate('date', '<=', $filters['to']))
            ->when(! empty($filters['account_id']), fn (Builder $q) => $q->where('account_id', $filters['account_id']))
            ->when(! empty($filters['category_id']), fn (Builder $q) => $q->where('category_id', $filters['category_id']));
    }
}
