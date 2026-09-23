<?php

namespace App\Http\Controllers\Household;

use App\Actions\Scenarios\CreateScenario;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreScenarioRequest;
use App\Models\Household;
use App\Models\Scenario;
use App\Services\NetWorth\ScenarioService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ScenarioController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private ScenarioService $scenarios) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $scenarios = Scenario::query()
            ->where('household_id', $household->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('household/Scenarios', [
            'scenarios' => $scenarios->map(fn (Scenario $scenario): array => $this->shape($scenario)),
            'comparison' => $this->comparison($request, $household, $scenarios),
            'defaults' => [
                'base_year' => (int) now()->year,
                'horizon_years' => 30,
                'return_bps' => 500,
                'inflation_bps' => 200,
            ],
        ]);
    }

    public function store(StoreScenarioRequest $request, CreateScenario $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scenario saved.')]);

        return to_route('household.scenarios.index');
    }

    public function destroy(Request $request, Scenario $scenario): RedirectResponse
    {
        abort_unless($scenario->household_id === $this->household($request)->id, 404);

        $scenario->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scenario removed.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Scenario $scenario): array
    {
        $projection = $this->scenarios->project($scenario);

        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'formula_version' => $projection['formula_version'],
            'assumptions_snapshot' => $scenario->assumptions_snapshot,
            'ending' => collect($projection['ending'])->map(fn (array $e): array => [
                'nominal' => Money::format($e['nominal']),
                'real' => Money::format($e['real']),
            ]),
        ];
    }

    /**
     * @param  Collection<int, Scenario>  $scenarios
     * @return array<string, mixed>|null
     */
    private function comparison(Request $request, Household $household, $scenarios): ?array
    {
        $a = $scenarios->firstWhere('id', $request->query('a'));
        $b = $scenarios->firstWhere('id', $request->query('b'));

        if ($a === null || $b === null || $a->is($b)) {
            return null;
        }

        return [
            'a' => ['id' => $a->id, 'name' => $a->name],
            'b' => ['id' => $b->id, 'name' => $b->name],
            'changes' => $this->scenarios->compare($a, $b),
        ];
    }
}
