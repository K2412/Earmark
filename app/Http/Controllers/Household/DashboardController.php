<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\UpdateOverviewCardsRequest;
use App\Models\Bucket;
use App\Models\Goal;
use App\Models\Holding;
use App\Models\RecurringSchedule;
use App\Models\Scenario;
use App\Services\Budget\BudgetService;
use App\Services\NetWorth\SnapshotService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesHousehold;

    public const CARDS = ['budget', 'review_queue', 'recurring', 'goals', 'net_worth', 'investments', 'scenarios'];

    public function __construct(
        private BudgetService $budget,
        private SnapshotService $snapshot,
    ) {}

    public function show(Request $request): Response
    {
        $household = $this->household($request);
        $year = (int) now()->year;
        $month = (int) now()->month;

        $unassigned = $household->buckets()->where('name', Bucket::UNASSIGNED_FUNDS)->first();
        $unassignedAvailable = $unassigned
            ? $this->budget->availableForMonth($unassigned, $year, $month)
            : 0;

        $underfunded = $this->budget->underfundedBuckets($year, $month, $household)
            ->map(fn (Bucket $bucket): array => ['id' => $bucket->id, 'name' => $bucket->name])
            ->values();

        $snapshot = $this->snapshot->forHousehold($household);
        $plan = $household->plans()->latest()->first();

        return Inertia::render('household/Overview', [
            'availableCards' => self::CARDS,
            'enabledCards' => $household->overview_cards ?? self::CARDS,
            'cards' => [
                'budget' => [
                    'unassigned' => Money::format($unassignedAvailable),
                    'underfunded' => $underfunded,
                ],
                'review_queue' => [
                    'count' => $household->transactions()->whereNull('transfer_pair_id')->where('reviewed', false)->count(),
                ],
                'recurring' => [
                    'count' => RecurringSchedule::query()->where('household_id', $household->id)->where('status', 'confirmed')->count(),
                ],
                'goals' => [
                    'count' => Goal::query()->where('household_id', $household->id)->where('status', 'active')->count(),
                ],
                'net_worth' => [
                    'investable' => $snapshot['investable'],
                    'total' => $snapshot['total_net_worth'],
                    'targetGap' => $plan ? Money::format($plan->target_cents - $snapshot['investable_cents']) : null,
                    'freshness' => $snapshot['freshness'],
                    'hasPositions' => $snapshot['has_positions'],
                ],
                'investments' => [
                    'total' => Money::format((int) Holding::query()->where('household_id', $household->id)->sum('market_value_cents')),
                    'count' => Holding::query()->where('household_id', $household->id)->count(),
                ],
                'scenarios' => [
                    'count' => Scenario::query()->where('household_id', $household->id)->count(),
                ],
            ],
        ]);
    }

    public function updateCards(UpdateOverviewCardsRequest $request): RedirectResponse
    {
        $household = $this->household($request);
        $household->update(['overview_cards' => $request->validated('cards')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Overview updated.')]);

        return back();
    }
}
