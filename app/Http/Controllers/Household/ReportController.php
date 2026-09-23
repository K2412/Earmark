<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\SaveReportRequest;
use App\Models\Bucket;
use App\Models\Household;
use App\Models\SavedReport;
use App\Services\Budget\BudgetService;
use App\Services\NetWorth\SnapshotService;
use App\Services\Reporting\ReportingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use ResolvesHousehold;

    private const TYPES = ['cash_flow', 'spending', 'income', 'budget', 'net_worth'];

    public function __construct(
        private ReportingService $reporting,
        private BudgetService $budget,
        private SnapshotService $snapshot,
    ) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'cash_flow';
        $filters = $this->filters($request);

        return Inertia::render('household/Reports', [
            'type' => $type,
            'filters' => $filters,
            'result' => $this->result($household, $type, $filters),
            'savedReports' => SavedReport::query()
                ->where('household_id', $household->id)
                ->orderBy('name')
                ->get()
                ->map(fn (SavedReport $report): array => [
                    'id' => $report->id,
                    'name' => $report->name,
                    'type' => $report->type,
                    'filters' => $report->filters,
                ]),
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'categories' => $household->categories()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'types' => self::TYPES,
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $household = $this->household($request);
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'cash_flow';
        $filters = $this->filters($request);

        [$headers, $rows] = $this->csvRows($household, $type, $filters);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$type.'-report.csv"',
        ]);
    }

    public function store(SaveReportRequest $request): RedirectResponse
    {
        $household = $this->household($request);

        SavedReport::query()->create([
            ...$request->validated(),
            'household_id' => $household->id,
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report saved.')]);

        return back();
    }

    public function destroy(Request $request, SavedReport $savedReport): RedirectResponse
    {
        abort_unless($savedReport->household_id === $this->household($request)->id, 404);

        $savedReport->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved report removed.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'account_id' => $request->string('account_id')->toString() ?: null,
            'category_id' => $request->string('category_id')->toString() ?: null,
            'year' => (int) $request->integer('year', now()->year),
            'month' => (int) $request->integer('month', now()->month),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function result(Household $household, string $type, array $filters): array
    {
        return match ($type) {
            'spending' => ['rows' => $this->formatCategory($this->reporting->byCategory($household, $filters, 'expense'))],
            'income' => ['rows' => $this->formatCategory($this->reporting->byCategory($household, $filters, 'income'))],
            'budget' => ['rows' => $this->budgetRows($household, $filters)],
            'net_worth' => ['rows' => collect($this->snapshot->history($household, 12))->map(fn (array $p): array => [
                'label' => $p['date'],
                'value' => Money::format($p['total_net_worth_cents']),
            ])->all()],
            default => $this->cashFlowResult($household, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cashFlowResult(Household $household, array $filters): array
    {
        $totals = $this->reporting->cashFlow($household, $filters);

        return [
            'income' => Money::format($totals['income_cents']),
            'expense' => Money::format($totals['expense_cents']),
            'net' => Money::format($totals['net_cents']),
            'drilldown' => collect($this->reporting->drilldown($household, $filters))->map(fn (array $r): array => [
                ...$r,
                'amount' => Money::format($r['amount_cents']),
            ])->all(),
        ];
    }

    /**
     * @param  list<array{category: string, total_cents: int}>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function formatCategory(array $rows): array
    {
        return collect($rows)->map(fn (array $r): array => [
            'label' => $r['category'],
            'value' => Money::format($r['total_cents']),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, value: string}>
     */
    private function budgetRows(Household $household, array $filters): array
    {
        return $household->buckets()->where('archived', false)->where('kind', '!=', 'system')->orderBy('name')->get()
            ->map(function (Bucket $bucket) use ($filters): array {
                $available = $this->budget->availableForMonth($bucket, $filters['year'], $filters['month']);
                $obligation = $this->budget->obligationForMonth($bucket, $filters['year'], $filters['month']);

                return [
                    'label' => $bucket->name,
                    'value' => Money::format($available - $obligation).' variance',
                ];
            })->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function csvRows(Household $household, string $type, array $filters): array
    {
        if (in_array($type, ['spending', 'income'], true)) {
            $rows = $this->reporting->byCategory($household, $filters, $type === 'income' ? 'income' : 'expense');

            return [['Category', 'Amount'], collect($rows)->map(fn (array $r): array => [$r['category'], Money::format($r['total_cents'])])->all()];
        }

        if ($type === 'net_worth') {
            return [['Month', 'Total'], collect($this->snapshot->history($household, 12))->map(fn (array $p): array => [$p['date'], Money::format($p['total_net_worth_cents'])])->all()];
        }

        if ($type === 'budget') {
            return [['Bucket', 'Variance'], collect($this->budgetRows($household, $filters))->map(fn (array $r): array => [$r['label'], $r['value']])->all()];
        }

        return [['Date', 'Account', 'Payee', 'Category', 'Amount'], collect($this->reporting->drilldown($household, $filters))->map(fn (array $r): array => [
            $r['date'], $r['account'] ?? '', $r['payee'], $r['category'] ?? '', Money::format($r['amount_cents']),
        ])->all()];
    }
}
