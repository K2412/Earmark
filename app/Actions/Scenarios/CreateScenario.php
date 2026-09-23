<?php

namespace App\Actions\Scenarios;

use App\Models\Household;
use App\Models\Scenario;
use App\Models\User;
use App\Services\Canadian\CanadianAssumptionCatalogue;
use App\Services\NetWorth\ScenarioService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates a scenario, snapshotting the Canadian assumption catalogue values in
 * effect at creation so the scenario stays reproducible and auditable even after
 * the catalogue changes (task #1259).
 */
class CreateScenario
{
    use AsAction;

    public function __construct(private CanadianAssumptionCatalogue $catalogue) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $user, Household $household): Scenario
    {
        // Optional numeric fields arrive as null from empty form inputs; their
        // columns are NOT NULL with a 0 default.
        foreach (['windfall_cents', 'drawdown_annual_cents', 'downsizing_proceeds_cents'] as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        $baseYear = (int) $data['base_year'];
        $snapshot = [];

        foreach (CanadianAssumptionCatalogue::KEYS as $key) {
            $snapshot[$key] = $this->catalogue->value($key, $baseYear, $household);
        }

        return Scenario::query()->create([
            ...$data,
            'household_id' => $household->id,
            'assumptions_snapshot' => $snapshot,
            'formula_version' => ScenarioService::FORMULA_VERSION,
            'created_by_user_id' => $user->id,
        ]);
    }
}
