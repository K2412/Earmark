<?php

namespace App\Services\Investment;

use App\Models\Holding;
use App\Models\Household;
use Illuminate\Support\Collection;

/**
 * Derives portfolio views from manually entered holdings — no brokerage sync.
 * Allocation reconciles to market values; performance is a simple return from
 * entered cost basis and market value, with methodology and limitations stated
 * by the caller (task #1260).
 */
class InvestmentPortfolioService
{
    public const METHODOLOGY = 'Simple return: (market value − cost basis) ÷ cost basis, from values you enter. Excludes fees and dividends; no live prices.';

    /**
     * @return array{total_market_cents: int, total_cost_cents: int, gain_cents: int, return_bps: int}
     */
    public function performance(Household $household): array
    {
        $holdings = $this->holdings($household);
        $market = (int) $holdings->sum('market_value_cents');
        $cost = (int) $holdings->sum('cost_basis_cents');
        $gain = $market - $cost;

        return [
            'total_market_cents' => $market,
            'total_cost_cents' => $cost,
            'gain_cents' => $gain,
            'return_bps' => $cost > 0 ? (int) round($gain / $cost * 10000) : 0,
        ];
    }

    /**
     * Allocation by asset class: actual share of total market value against the
     * user's target. Actual shares reconcile to market values (sum to 100%).
     *
     * @return list<array{asset_class: string, market_cents: int, actual_bps: int, target_bps: int}>
     */
    public function allocation(Household $household): array
    {
        $holdings = $this->holdings($household);
        $total = (int) $holdings->sum('market_value_cents');

        return $holdings
            ->groupBy('asset_class')
            ->map(fn ($group, string $assetClass): array => [
                'asset_class' => $assetClass,
                'market_cents' => (int) $group->sum('market_value_cents'),
                'actual_bps' => $total > 0 ? (int) round($group->sum('market_value_cents') / $total * 10000) : 0,
                'target_bps' => (int) $group->sum('target_allocation_bps'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Holding>
     */
    private function holdings(Household $household)
    {
        return Holding::query()->where('household_id', $household->id)->get();
    }
}
