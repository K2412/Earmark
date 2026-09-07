<?php

namespace App\Services\NetWorth;

use App\Models\ContributionPhase;
use App\Models\HouseholdPlan;
use App\Support\Money;
use Illuminate\Support\Collection;

class ProjectionService
{
    public const FORMULA_VERSION = 'earmark.projection.v1';

    public const SENSITIVITY_BPS = [500, 700, 900];

    /**
     * @return array<string, mixed>|null
     */
    public function project(int $startingInvestableCents, HouseholdPlan $plan, int $currentYear): ?array
    {
        if ($plan->target_year < $currentYear) {
            return null;
        }

        $phases = $plan->contributionPhases;
        $series = [
            'base' => $this->run($startingInvestableCents, $plan, $currentYear, $plan->return_bps, $phases),
        ];

        foreach (self::SENSITIVITY_BPS as $bps) {
            $series['bps_'.$bps] = $this->run($startingInvestableCents, $plan, $currentYear, $bps, $phases);
        }

        $inflatedTarget = $this->inflate($plan->target_cents, $plan->inflation_bps, $plan->target_year - $currentYear);
        $baseAtTarget = collect($series['base'])->last()['cents'];

        return [
            'formula_version' => self::FORMULA_VERSION,
            'labels' => [
                'values' => 'before tax',
                'return' => 'after fees',
            ],
            'starting_cents' => $startingInvestableCents,
            'starting' => Money::format($startingInvestableCents),
            'target_cents' => $plan->target_cents,
            'target' => Money::format($plan->target_cents),
            'inflated_target_cents' => $inflatedTarget,
            'inflated_target' => Money::format($inflatedTarget),
            'target_year' => $plan->target_year,
            'projected_at_target_cents' => $baseAtTarget,
            'projected_at_target' => Money::format($baseAtTarget),
            'gap_cents' => $inflatedTarget - $baseAtTarget,
            'gap' => Money::format($inflatedTarget - $baseAtTarget),
            'series' => $series,
            'table' => $this->table($series),
        ];
    }

    /**
     * @param  Collection<int, ContributionPhase>  $phases
     * @return list<array{year: int, cents: int, amount: string}>
     */
    private function run(int $starting, HouseholdPlan $plan, int $currentYear, int $returnBps, Collection $phases): array
    {
        $points = [[
            'year' => $currentYear,
            'cents' => $starting,
            'amount' => Money::format($starting),
        ]];

        $balance = $starting;

        for ($year = $currentYear + 1; $year <= $plan->target_year; $year++) {
            $balance += $this->applyBps($balance, $returnBps);
            $balance += $this->contributionFor($phases, $year);

            if ($plan->windfall_year === $year) {
                $balance += $plan->windfall_cents;
            }

            $points[] = [
                'year' => $year,
                'cents' => $balance,
                'amount' => Money::format($balance),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, ContributionPhase>  $phases
     */
    private function contributionFor(Collection $phases, int $year): int
    {
        $phase = $phases->first(
            fn (ContributionPhase $phase) => $year >= $phase->start_year && $year <= $phase->end_year,
        );

        return $phase?->annual_contribution_cents ?? 0;
    }

    public function applyBps(int $cents, int $bps): int
    {
        $product = $cents * $bps;
        $rounding = $product >= 0 ? 5_000 : -5_000;

        return intdiv($product + $rounding, 10_000);
    }

    public function inflate(int $cents, int $inflationBps, int $years): int
    {
        $value = $cents;

        for ($i = 0; $i < $years; $i++) {
            $value += $this->applyBps($value, $inflationBps);
        }

        return $value;
    }

    /**
     * @param  array<string, list<array{year: int, cents: int, amount: string}>>  $series
     * @return list<array<string, mixed>>
     */
    private function table(array $series): array
    {
        $years = collect($series['base'])->pluck('year');

        return $years->map(function (int $year) use ($series) {
            $row = ['year' => $year];

            foreach ($series as $key => $points) {
                $point = collect($points)->firstWhere('year', $year);
                $row[$key] = $point['amount'];
                $row[$key.'_cents'] = $point['cents'];
            }

            return $row;
        })->values()->all();
    }
}
