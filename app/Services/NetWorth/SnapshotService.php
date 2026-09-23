<?php

namespace App\Services\NetWorth;

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Models\FinancialPosition;
use App\Models\Household;
use App\Models\Valuation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SnapshotService
{
    public const STALE_AFTER_DAYS = 90;

    /**
     * @param  array{owner_user_id?: ?string, purpose?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function forHousehold(Household $household, ?CarbonImmutable $asOf = null, array $filters = []): array
    {
        $asOf ??= CarbonImmutable::now();
        $staleBefore = $asOf->subDays(self::STALE_AFTER_DAYS)->toDateString();

        $positions = $this->positions($household, $filters);

        $rows = $positions->map(function (FinancialPosition $position) use ($asOf) {
            $valuation = $this->latestValuation($position, $asOf);

            return [
                'position' => $position,
                'valuation' => $valuation,
                'signed_cents' => $this->signedCents($position, $valuation),
            ];
        });

        $investable = $this->sumAssets($rows, PositionPurpose::Investable);
        $homePurchase = $this->sumAssets($rows, PositionPurpose::HomePurchase);
        $homeAssets = $this->sumAssets($rows, PositionPurpose::PrimaryResidence);
        $homeLiabilities = $this->sumLiabilities($rows, PositionPurpose::PrimaryResidence);
        $totalAssets = $rows
            ->filter(fn (array $row) => $row['position']->classification === PositionClassification::Asset)
            ->sum('signed_cents');
        $totalLiabilities = $rows
            ->filter(fn (array $row) => $row['position']->classification === PositionClassification::Liability)
            ->sum(fn (array $row) => abs($row['signed_cents']));

        $valued = $rows->pluck('valuation')->filter();
        $oldest = $valued->min(fn (Valuation $valuation) => $valuation->valued_at?->toDateString());
        $newest = $valued->max(fn (Valuation $valuation) => $valuation->valued_at?->toDateString());
        $staleCount = $valued
            ->filter(fn (Valuation $valuation) => $valuation->valued_at?->toDateString() < $staleBefore)
            ->count();

        $status = match (true) {
            $valued->isEmpty() => 'unknown',
            $staleCount > 0 => 'stale',
            default => 'current',
        };

        return [
            'calculated_at' => $asOf->toIso8601String(),
            'has_positions' => $positions->isNotEmpty(),
            'investable_cents' => $investable,
            'home_purchase_cents' => $homePurchase,
            'home_equity_cents' => $homeAssets - $homeLiabilities,
            'total_net_worth_cents' => $totalAssets - $totalLiabilities,
            'investable' => Money::format($investable),
            'home_purchase' => Money::format($homePurchase),
            'home_equity' => Money::format($homeAssets - $homeLiabilities),
            'total_net_worth' => Money::format($totalAssets - $totalLiabilities),
            'freshness' => [
                'oldest_valued_at' => $oldest,
                'newest_valued_at' => $newest,
                'stale_count' => $staleCount,
                'status' => $status,
                'label' => match ($status) {
                    'stale' => $staleCount === 1 ? '1 stale valuation' : "{$staleCount} stale valuations",
                    'current' => 'Valuations current',
                    default => 'No valuations yet',
                },
            ],
            'positions' => $rows->map(function (array $row) {
                /** @var FinancialPosition $position */
                $position = $row['position'];
                /** @var Valuation|null $valuation */
                $valuation = $row['valuation'];

                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'classification' => $position->classification->value,
                    'purpose' => $position->purpose->value,
                    'owner' => $position->owner?->name,
                    'amount' => $valuation ? Money::format($valuation->amount) : '—',
                    'amount_cents' => $valuation?->amount,
                    'valued_at' => $valuation?->valued_at?->toDateString(),
                ];
            })->values(),
        ];
    }

    /**
     * @param  Collection<int, array{position: FinancialPosition, valuation: ?Valuation, signed_cents: int}>  $rows
     */
    private function sumAssets(Collection $rows, PositionPurpose $purpose): int
    {
        return $rows
            ->filter(fn (array $row) => $row['position']->classification === PositionClassification::Asset
                && $row['position']->purpose === $purpose)
            ->sum('signed_cents');
    }

    /**
     * @param  Collection<int, array{position: FinancialPosition, valuation: ?Valuation, signed_cents: int}>  $rows
     */
    private function sumLiabilities(Collection $rows, PositionPurpose $purpose): int
    {
        return $rows
            ->filter(fn (array $row) => $row['position']->classification === PositionClassification::Liability
                && $row['position']->purpose === $purpose)
            ->sum(fn (array $row) => abs($row['signed_cents']));
    }

    /**
     * The most recent non-archived valuation on or before the as-of date, so
     * historical snapshots reconcile to the selected date.
     */
    private function latestValuation(FinancialPosition $position, CarbonImmutable $asOf): ?Valuation
    {
        return $position->valuations
            ->reject(fn (Valuation $valuation) => $valuation->archived)
            ->filter(fn (Valuation $valuation) => $valuation->valued_at !== null
                && $valuation->valued_at->toDateString() <= $asOf->toDateString())
            ->sortBy([
                ['valued_at', 'desc'],
                ['id', 'desc'],
            ])
            ->first();
    }

    /**
     * @param  array{owner_user_id?: ?string, purpose?: ?string}  $filters
     * @return Collection<int, FinancialPosition>
     */
    private function positions(Household $household, array $filters = []): Collection
    {
        return $household->financialPositions()
            ->where('archived', false)
            ->when(! empty($filters['owner_user_id']), fn ($q) => $q->where('owner_user_id', $filters['owner_user_id']))
            ->when(! empty($filters['purpose']), fn ($q) => $q->where('purpose', $filters['purpose']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['valuations', 'owner'])
            ->get();
    }

    /**
     * A month-end net-worth trend for the last N months, preserving the
     * investable / home-equity / home-purchase distinctions at each point.
     *
     * @param  array{owner_user_id?: ?string, purpose?: ?string}  $filters
     * @return list<array{date: string, investable_cents: int, home_equity_cents: int, home_purchase_cents: int, total_net_worth_cents: int}>
     */
    public function history(Household $household, int $months = 12, array $filters = []): array
    {
        $positions = $this->positions($household, $filters);
        $now = CarbonImmutable::now();
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $asOf = $now->subMonths($i)->endOfMonth();

            $rows = $positions->map(fn (FinancialPosition $position): array => [
                'position' => $position,
                'valuation' => $this->latestValuation($position, $asOf),
                'signed_cents' => $this->signedCents($position, $this->latestValuation($position, $asOf)),
            ]);

            $homeAssets = $this->sumAssets($rows, PositionPurpose::PrimaryResidence);
            $homeLiabilities = $this->sumLiabilities($rows, PositionPurpose::PrimaryResidence);
            $totalAssets = $rows->filter(fn (array $row) => $row['position']->classification === PositionClassification::Asset)->sum('signed_cents');
            $totalLiabilities = $rows->filter(fn (array $row) => $row['position']->classification === PositionClassification::Liability)->sum(fn (array $row) => abs($row['signed_cents']));

            $series[] = [
                'date' => $asOf->toDateString(),
                'investable_cents' => $this->sumAssets($rows, PositionPurpose::Investable),
                'home_purchase_cents' => $this->sumAssets($rows, PositionPurpose::HomePurchase),
                'home_equity_cents' => $homeAssets - $homeLiabilities,
                'total_net_worth_cents' => $totalAssets - $totalLiabilities,
            ];
        }

        return $series;
    }

    private function signedCents(FinancialPosition $position, ?Valuation $valuation): int
    {
        $amount = $valuation?->amount ?? 0;

        return $position->classification === PositionClassification::Liability
            ? -$amount
            : $amount;
    }
}
