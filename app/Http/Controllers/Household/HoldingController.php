<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreHoldingRequest;
use App\Http\Requests\Household\UpdateHoldingRequest;
use App\Models\Holding;
use App\Services\Investment\InvestmentPortfolioService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HoldingController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private InvestmentPortfolioService $portfolio) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $holdings = Holding::query()
            ->where('household_id', $household->id)
            ->orderBy('asset_class')->orderBy('name')
            ->get()
            ->map(function (Holding $holding): array {
                $gain = $holding->market_value_cents - $holding->cost_basis_cents;

                return [
                    'id' => $holding->id,
                    'name' => $holding->name,
                    'symbol' => $holding->symbol,
                    'asset_class' => $holding->asset_class,
                    'cost_basis' => Money::format($holding->cost_basis_cents),
                    'market_value' => Money::format($holding->market_value_cents),
                    'cost_basis_cents' => $holding->cost_basis_cents,
                    'market_value_cents' => $holding->market_value_cents,
                    'target_allocation_bps' => $holding->target_allocation_bps,
                    'gain' => Money::format($gain),
                    'account_id' => $holding->account_id,
                ];
            });

        $performance = $this->portfolio->performance($household);
        $allocation = collect($this->portfolio->allocation($household))->map(fn (array $a): array => [
            'asset_class' => $a['asset_class'],
            'market' => Money::format($a['market_cents']),
            'actual_percent' => number_format($a['actual_bps'] / 100, 1),
            'target_percent' => $a['target_bps'] > 0 ? number_format($a['target_bps'] / 100, 1) : null,
        ]);

        return Inertia::render('household/Holdings', [
            'holdings' => $holdings,
            'allocation' => $allocation,
            'performance' => [
                'total_market' => Money::format($performance['total_market_cents']),
                'total_cost' => Money::format($performance['total_cost_cents']),
                'gain' => Money::format($performance['gain_cents']),
                'return_percent' => number_format($performance['return_bps'] / 100, 2),
            ],
            'methodology' => InvestmentPortfolioService::METHODOLOGY,
            'assetClasses' => Holding::ASSET_CLASSES,
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreHoldingRequest $request): RedirectResponse
    {
        $household = $this->household($request);

        Holding::query()->create([
            ...$request->validated(),
            'household_id' => $household->id,
            'currency' => 'CAD',
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holding added.')]);

        return to_route('household.holdings.index');
    }

    public function update(UpdateHoldingRequest $request, Holding $holding): RedirectResponse
    {
        $holding->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holding updated.')]);

        return back();
    }

    public function destroy(Request $request, Holding $holding): RedirectResponse
    {
        abort_unless($holding->household_id === $this->household($request)->id, 404);

        $holding->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holding removed.')]);

        return back();
    }
}
