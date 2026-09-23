<?php

namespace App\Services\NetWorth;

use App\Models\Scenario;

/**
 * Projects a saved scenario deterministically from its recorded inputs and
 * assumptions snapshot — the same scenario always yields the same result, and a
 * later catalogue change never rewrites it. Produces conservative/base/optimistic
 * sensitivities in both nominal and today's-dollar terms. Investable growth never
 * includes home equity unless an explicit downsizing event transfers proceeds in
 * (task #1259).
 */
class ScenarioService
{
    public const FORMULA_VERSION = 'earmark.scenario.v1';

    /** Return adjustment (bps) per sensitivity band. */
    public const SENSITIVITIES = ['conservative' => -200, 'base' => 0, 'optimistic' => 200];

    /**
     * @return array{formula_version: string, series: array<string, list<array{year: int, nominal_cents: int, real_cents: int}>>, ending: array<string, array{nominal: int, real: int}>}
     */
    public function project(Scenario $scenario): array
    {
        $series = [];
        $ending = [];

        foreach (self::SENSITIVITIES as $band => $adjustment) {
            $rate = max(0, $scenario->return_bps + $adjustment) / 10000;
            $inflation = $scenario->inflation_bps / 10000;
            $balance = $scenario->starting_investable_cents;
            $points = [];

            for ($year = $scenario->base_year; $year <= $scenario->base_year + $scenario->horizon_years; $year++) {
                if ($year > $scenario->base_year) {
                    $balance = (int) round($balance * (1 + $rate));

                    if ($year - $scenario->base_year <= $scenario->contribution_years) {
                        $balance += $scenario->annual_contribution_cents;
                    }

                    if ($scenario->windfall_year === $year) {
                        $balance += $scenario->windfall_cents;
                    }

                    // Home equity only enters investable through an explicit downsizing/sale event.
                    if ($scenario->downsizing_year === $year) {
                        $balance += $scenario->downsizing_proceeds_cents;
                    }

                    if ($scenario->drawdown_start_year !== null && $year >= $scenario->drawdown_start_year) {
                        $balance -= $scenario->drawdown_annual_cents;
                    }

                    $balance = max(0, $balance);
                }

                $yearsOut = $year - $scenario->base_year;
                $real = (int) round($balance / (($inflation > 0) ? (1 + $inflation) ** $yearsOut : 1));

                $points[] = ['year' => $year, 'nominal_cents' => $balance, 'real_cents' => $real];
            }

            $series[$band] = $points;
            $last = end($points);
            $ending[$band] = ['nominal' => $last['nominal_cents'], 'real' => $last['real_cents']];
        }

        return [
            'formula_version' => self::FORMULA_VERSION,
            'series' => $series,
            'ending' => $ending,
        ];
    }

    /**
     * Highlight the inputs and snapshot assumptions that differ between two scenarios.
     *
     * @return list<array{field: string, a: mixed, b: mixed}>
     */
    public function compare(Scenario $a, Scenario $b): array
    {
        $fields = [
            'starting_investable_cents', 'annual_contribution_cents', 'contribution_years',
            'return_bps', 'inflation_bps', 'windfall_cents', 'windfall_year',
            'drawdown_start_year', 'drawdown_annual_cents', 'downsizing_year', 'downsizing_proceeds_cents',
        ];

        $changes = [];

        foreach ($fields as $field) {
            if ($a->getAttribute($field) !== $b->getAttribute($field)) {
                $changes[] = ['field' => $field, 'a' => $a->getAttribute($field), 'b' => $b->getAttribute($field)];
            }
        }

        return $changes;
    }
}
