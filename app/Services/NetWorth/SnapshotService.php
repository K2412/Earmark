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
     * @return array<string, mixed>
     */
    public function forHousehold(Household $household, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now();
        $staleBefore = $asOf->subDays(self::STALE_AFTER_DAYS)->toDateString();

        $positions = $household->financialPositions()
            ->where('archived', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['valuations', 'owner'])
            ->get();

        $rows = $positions->map(function (FinancialPosition $position) {
            $valuation = $this->latestValuation($position);

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

    private function latestValuation(FinancialPosition $position): ?Valuation
    {
        return $position->valuations
            ->sortBy([
                ['valued_at', 'desc'],
                ['id', 'desc'],
            ])
            ->first();
    }

    private function signedCents(FinancialPosition $position, ?Valuation $valuation): int
    {
        $amount = $valuation?->amount ?? 0;

        return $position->classification === PositionClassification::Liability
            ? -$amount
            : $amount;
    }
}
