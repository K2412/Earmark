<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreFinancialPositionRequest;
use App\Http\Requests\Household\StoreHouseholdPlanRequest;
use App\Http\Requests\Household\StoreValuationRequest;
use App\Models\FinancialPosition;
use App\Services\NetWorth\ProjectionService;
use App\Services\NetWorth\SnapshotService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NetWorthController extends Controller
{
    use ResolvesHousehold;

    public function __construct(
        private SnapshotService $snapshot,
        private ProjectionService $projection,
    ) {}

    public function show(Request $request): Response
    {
        $household = $this->household($request);
        $snapshot = $this->snapshot->forHousehold($household);
        $plan = $household->plans()->with('contributionPhases')->latest()->first();

        $projection = $snapshot['has_positions'] && $plan
            ? $this->projection->project($snapshot['investable_cents'], $plan, (int) now()->year)
            : null;

        $targetGap = $plan
            ? Money::format($plan->target_cents - $snapshot['investable_cents'])
            : null;

        return Inertia::render('household/NetWorth', [
            'snapshot' => $snapshot,
            'plan' => $plan ? [
                'target_cents' => $plan->target_cents,
                'target_year' => $plan->target_year,
                'inflation_bps' => $plan->inflation_bps,
                'return_bps' => $plan->return_bps,
                'windfall_cents' => $plan->windfall_cents,
                'windfall_year' => $plan->windfall_year,
                'phases' => $plan->contributionPhases->map(fn ($phase) => [
                    'start_year' => $phase->start_year,
                    'end_year' => $phase->end_year,
                    'annual_contribution_cents' => $phase->annual_contribution_cents,
                ])->values(),
            ] : null,
            'projection' => $projection,
            'targetGap' => $targetGap,
            'defaults' => [
                'valued_at' => now()->toDateString(),
                'target_year' => now()->addYears(30)->year,
                'phase_start' => now()->year,
                'phase_end' => now()->addYears(30)->year,
            ],
            'classifications' => collect(PositionClassification::cases())->map(fn ($case) => [
                'value' => $case->value,
                'label' => ucfirst($case->value),
            ]),
            'purposes' => collect(PositionPurpose::cases())->map(fn ($case) => [
                'value' => $case->value,
                'label' => str_replace('_', ' ', $case->value),
            ]),
        ]);
    }

    public function storePosition(StoreFinancialPositionRequest $request): RedirectResponse
    {
        $household = $this->household($request);
        $data = $request->validated();

        DB::transaction(function () use ($household, $data, $request) {
            $position = FinancialPosition::query()->create([
                'household_id' => $household->id,
                'name' => $data['name'],
                'classification' => $data['classification'],
                'purpose' => $data['purpose'],
                'owner_user_id' => $data['owner_user_id'] ?? null,
            ]);

            $position->valuations()->create([
                'amount' => $data['amount'],
                'valued_at' => $data['valued_at'],
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Financial position added.')]);

        return to_route('household.net-worth.show');
    }

    public function storeValuation(StoreValuationRequest $request, FinancialPosition $position): RedirectResponse
    {
        $household = $this->household($request);

        abort_unless($position->household_id === $household->id, 403);

        $position->valuations()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Valuation recorded.')]);

        return to_route('household.net-worth.show');
    }

    public function storePlan(StoreHouseholdPlanRequest $request): RedirectResponse
    {
        $household = $this->household($request);
        $data = $request->validated();

        DB::transaction(function () use ($household, $data) {
            $attributes = [
                'target_cents' => $data['target_cents'],
                'target_year' => $data['target_year'],
                'inflation_bps' => $data['inflation_bps'],
                'return_bps' => $data['return_bps'],
                'windfall_cents' => $data['windfall_cents'] ?? 0,
                'windfall_year' => $data['windfall_year'] ?? null,
            ];

            $plan = $household->plans()->latest()->first();
            $plan
                ? $plan->update($attributes)
                : $plan = $household->plans()->create($attributes);

            $plan->contributionPhases()->delete();

            foreach ($data['phases'] as $index => $phase) {
                $plan->contributionPhases()->create([
                    ...$phase,
                    'sort_order' => $index,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Household plan saved.')]);

        return to_route('household.net-worth.show');
    }
}
