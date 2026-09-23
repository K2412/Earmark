<?php

namespace App\Services\Canadian;

use App\Models\Assumption;
use App\Models\Household;
use Carbon\CarbonImmutable;

/**
 * Resolves effective-dated, source-labelled planning assumptions. For a key and
 * year it returns the most recent applicable value, preferring a household
 * override over the system default of the same vintage. Calculators consume these
 * explicit versioned values so a later catalogue change never rewrites a scenario
 * that recorded the value it used (task #1258).
 */
class CanadianAssumptionCatalogue
{
    public const STALE_AFTER_MONTHS = 15;

    public const KEYS = ['tfsa_limit', 'rrsp_limit', 'fhsa_limit', 'inflation_rate', 'cpp_max_monthly', 'oas_max_monthly'];

    public function resolve(string $key, int $year, Household $household, string $jurisdiction = 'CA'): ?Assumption
    {
        return Assumption::query()
            ->where('key', $key)
            ->where('jurisdiction', $jurisdiction)
            ->where('effective_year', '<=', $year)
            ->where(function ($query) use ($household) {
                $query->whereNull('household_id')->orWhere('household_id', $household->id);
            })
            ->orderByDesc('effective_year')
            ->orderByRaw('CASE WHEN household_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    public function value(string $key, int $year, Household $household): ?int
    {
        return $this->resolve($key, $year, $household)?->value;
    }

    public function isStale(Assumption $assumption, ?CarbonImmutable $asOf = null): bool
    {
        $asOf ??= CarbonImmutable::now();

        if ($assumption->source_date === null) {
            return true;
        }

        return $assumption->source_date->lt($asOf->subMonths(self::STALE_AFTER_MONTHS));
    }

    /**
     * @return list<array{key: string, assumption: ?Assumption, stale: bool}>
     */
    public function all(Household $household, int $year): array
    {
        return array_map(function (string $key) use ($household, $year): array {
            $assumption = $this->resolve($key, $year, $household);

            return [
                'key' => $key,
                'assumption' => $assumption,
                'stale' => $assumption !== null && $this->isStale($assumption),
            ];
        }, self::KEYS);
    }
}
