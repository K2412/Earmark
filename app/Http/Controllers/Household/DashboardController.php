<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Models\Bucket;
use App\Services\Budget\BudgetService;
use App\Services\NetWorth\SnapshotService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesHousehold;

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
            ->map(fn (Bucket $bucket) => [
                'id' => $bucket->id,
                'name' => $bucket->name,
            ])
            ->values();

        $snapshot = $this->snapshot->forHousehold($household);
        $plan = $household->plans()->latest()->first();

        return Inertia::render('household/Overview', [
            'unassignedAvailable' => Money::format($unassignedAvailable),
            'unassignedAvailableCents' => $unassignedAvailable,
            'underfundedBuckets' => $underfunded,
            'netWorth' => [
                'investable' => $snapshot['investable'],
                'total' => $snapshot['total_net_worth'],
                'targetGap' => $plan
                    ? Money::format($plan->target_cents - $snapshot['investable_cents'])
                    : null,
                'freshness' => $snapshot['freshness'],
                'hasPositions' => $snapshot['has_positions'],
            ],
        ]);
    }
}
