<?php

namespace App\Services\Budget;

use App\Models\Bucket;
use App\Models\BucketAssignment;
use App\Models\BucketObligationVersion;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pure-PHP envelope-budget math. No HTTP coupling.
 *
 * Money is in integer cents throughout. Months are (year, month) pairs.
 */
class BudgetService
{
    public function obligationForMonth(Bucket $bucket, int $year, int $month): int
    {
        $version = BucketObligationVersion::query()
            ->where('bucket_id', $bucket->id)
            ->where(function ($q) use ($year, $month) {
                $q->where('effective_year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $month) {
                        $q2->where('effective_year', $year)
                            ->where('effective_month', '<=', $month);
                    });
            })
            ->orderByDesc('effective_year')
            ->orderByDesc('effective_month')
            ->first();

        return $version?->monthly_obligation ?? (int) $bucket->monthly_obligation;
    }

    public function availableForMonth(Bucket $bucket, int $year, int $month): int
    {
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth();

        $assignmentsIn = (int) BucketAssignment::query()
            ->where('to_bucket_id', $bucket->id)
            ->where(function ($q) use ($year, $month) {
                $q->where('year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $month) {
                        $q2->where('year', $year)->where('month', '<=', $month);
                    });
            })
            ->sum('amount');

        $assignmentsOut = (int) BucketAssignment::query()
            ->where('from_bucket_id', $bucket->id)
            ->where(function ($q) use ($year, $month) {
                $q->where('year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $month) {
                        $q2->where('year', $year)->where('month', '<=', $month);
                    });
            })
            ->sum('amount');

        $transactionTotal = (int) Transaction::query()
            ->where('bucket_id', $bucket->id)
            ->where('date', '<=', $endOfMonth->toDateString())
            ->sum('amount');

        $splitTotal = (int) TransactionSplit::query()
            ->where('bucket_id', $bucket->id)
            ->whereHas('transaction', function ($q) use ($endOfMonth) {
                $q->where('date', '<=', $endOfMonth->toDateString());
            })
            ->sum('amount');

        return $assignmentsIn - $assignmentsOut + $transactionTotal + $splitTotal;
    }

    public function carryoverIntoMonth(Bucket $bucket, int $year, int $month): int
    {
        $prior = CarbonImmutable::createFromDate($year, $month, 1)->subMonth();

        return $this->availableForMonth($bucket, $prior->year, $prior->month);
    }

    public function rolledForwardObligation(Bucket $bucket, int $year, int $month): int
    {
        $firstVersion = BucketObligationVersion::query()
            ->where('bucket_id', $bucket->id)
            ->orderBy('effective_year')
            ->orderBy('effective_month')
            ->first();

        if (! $firstVersion) {
            return 0;
        }

        $rolled = 0;
        $cursor = CarbonImmutable::createFromDate($firstVersion->effective_year, $firstVersion->effective_month, 1)->startOfDay();
        $targetKey = $year * 100 + $month;

        while (($cursor->year * 100 + $cursor->month) < $targetKey) {
            $obligation = $this->obligationForMonth($bucket, $cursor->year, $cursor->month);
            $assignedIn = (int) BucketAssignment::query()
                ->where('to_bucket_id', $bucket->id)
                ->where('year', $cursor->year)
                ->where('month', $cursor->month)
                ->sum('amount');

            $gap = max(0, $obligation - $assignedIn);
            $rolled += $gap;

            $cursor = $cursor->addMonth();
        }

        return $rolled;
    }

    public function isUnderfunded(Bucket $bucket, int $year, int $month): bool
    {
        $available = $this->availableForMonth($bucket, $year, $month);
        $need = $this->obligationForMonth($bucket, $year, $month)
            + $this->rolledForwardObligation($bucket, $year, $month);

        return $available < $need;
    }

    /**
     * @return Collection<int, Bucket>
     */
    public function underfundedBuckets(int $year, int $month, ?Household $household = null)
    {
        $query = Bucket::query()
            ->where('archived', false)
            ->where('kind', '!=', 'system');

        if ($household) {
            $query->where('household_id', $household->id);
        }

        return $query
            ->get()
            ->filter(fn (Bucket $b) => $this->isUnderfunded($b, $year, $month))
            ->values();
    }
}
