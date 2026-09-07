<?php

namespace App\Http\Controllers\Household;

use App\Actions\Buckets\CreateBucket;
use App\Actions\Categories\CreateCategory;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreBucketRequest;
use App\Http\Requests\Household\StoreCategoryRequest;
use App\Models\Bucket;
use App\Services\Budget\BudgetService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private BudgetService $budget) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);
        $cursor = $this->cursor($request);
        $year = $cursor->year;
        $month = $cursor->month;

        $buckets = $household->buckets()
            ->where('archived', false)
            ->orderByRaw("CASE kind WHEN 'system' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $rows = $buckets->map(function (Bucket $bucket) use ($year, $month) {
            $available = $this->budget->availableForMonth($bucket, $year, $month);
            $obligation = $this->budget->obligationForMonth($bucket, $year, $month);
            $rolled = $this->budget->rolledForwardObligation($bucket, $year, $month);
            $needed = $obligation + $rolled;

            return [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'kind' => $bucket->kind,
                'obligation' => Money::format($obligation),
                'rolled' => Money::format($rolled),
                'needed' => Money::format($needed),
                'available' => Money::format($available),
                'available_cents' => $available,
                'needed_cents' => $needed,
            ];
        })->values();

        $categories = $household->categories()
            ->where('archived', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $previous = $cursor->subMonth();
        $next = $cursor->addMonth();

        return Inertia::render('household/Plan', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $cursor->format('F Y'),
            'rows' => $rows,
            'categories' => $categories,
            'previous' => ['year' => $previous->year, 'month' => $previous->month],
            'next' => ['year' => $next->year, 'month' => $next->month],
            'defaults' => [
                'target_date' => now()->addYear()->toDateString(),
            ],
        ]);
    }

    public function storeBucket(StoreBucketRequest $request, CreateBucket $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bucket created.')]);

        return to_route('household.plan.index', $request->only(['year', 'month']));
    }

    public function storeCategory(StoreCategoryRequest $request, CreateCategory $action): RedirectResponse
    {
        $action->handle($request->validated(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('household.plan.index', $request->only(['year', 'month']));
    }

    private function cursor(Request $request): CarbonImmutable
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);

        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 404);

        return CarbonImmutable::createFromDate($year, $month, 1);
    }
}
