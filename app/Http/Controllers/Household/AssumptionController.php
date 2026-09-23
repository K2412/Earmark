<?php

namespace App\Http\Controllers\Household;

use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\OverrideAssumptionRequest;
use App\Models\Assumption;
use App\Services\Canadian\CanadianAssumptionCatalogue;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssumptionController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private CanadianAssumptionCatalogue $catalogue) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);
        $year = (int) now()->year;

        $rows = collect($this->catalogue->all($household, $year))->map(function (array $entry): array {
            $assumption = $entry['assumption'];

            return [
                'key' => $entry['key'],
                'value' => $assumption ? $this->format($assumption) : '—',
                'raw_value' => $assumption?->value,
                'unit' => $assumption?->unit,
                'effective_year' => $assumption?->effective_year,
                'source_url' => $assumption?->source_url,
                'source_date' => $assumption?->source_date?->toDateString(),
                'catalogue_version' => $assumption?->catalogue_version,
                'jurisdiction' => $assumption?->jurisdiction,
                'is_override' => $assumption?->isOverride() ?? false,
                'stale' => $entry['stale'],
            ];
        });

        return Inertia::render('household/Assumptions', [
            'assumptions' => $rows,
            'keys' => CanadianAssumptionCatalogue::KEYS,
            'year' => $year,
            'defaults' => ['effective_year' => $year, 'source_date' => now()->toDateString()],
        ]);
    }

    public function store(OverrideAssumptionRequest $request): RedirectResponse
    {
        $household = $this->household($request);

        Assumption::query()->create([
            ...$request->validated(),
            'household_id' => $household->id,
            'jurisdiction' => 'CA',
            'catalogue_version' => 'household-override',
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assumption override saved.')]);

        return back();
    }

    private function format(Assumption $assumption): string
    {
        return match ($assumption->unit) {
            'cents' => Money::format($assumption->value),
            'bps' => number_format($assumption->value / 100, 2).'%',
            default => (string) $assumption->value,
        };
    }
}
