<?php

namespace App\Services\Budget;

use App\Models\Bucket;
use App\Models\BucketAssignment;
use App\Models\BucketObligationVersion;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pure-PHP envelope-budget math. No Livewire / HTTP coupling.
 *
 * Money is in integer cents throughout. Months are (year, month) pairs.
 *
 * Available balance for a bucket up to the end of (year, month):
 *   sum(assignments INTO bucket) - sum(assignments OUT of bucket)
 *   + sum(transaction amounts categorized to bucket, including splits)
 *
 * (Transaction amounts are negative for spend, positive for income, so summing
 *  them gives the net effect on the bucket's available cents.)
 */
class BudgetService
{
    /**
     * The latest-effective obligation amount for a bucket in (year, month).
     * Returns 0 if no obligation version is effective on or before that month.
     */
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

    /**
     * Available cents in a bucket as of the end of (year, month). This is the
     * cumulative balance — prior months' carryover is implicit because we sum
     * from the beginning of time up to month-end.
     */
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

    /**
     * Carryover INTO (year, month) — i.e. the available balance as of the end
     * of the *prior* month. By definition this is availableForMonth one month
     * earlier.
     */
    public function carryoverIntoMonth(Bucket $bucket, int $year, int $month): int
    {
        $prior = CarbonImmutable::createFromDate($year, $month, 1)->subMonth();

        return $this->availableForMonth($bucket, $prior->year, $prior->month);
    }

    /**
     * Sum of unmet obligation gaps from the bucket's first obligation through
     * the prior month. If a month's assignments-in fell short of the
     * obligation, the gap rolls forward.
     */
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

    /**
     * A bucket is underfunded for (year, month) when its available balance
     * cannot cover the obligation plus rolled-forward shortfall.
     */
    public function isUnderfunded(Bucket $bucket, int $year, int $month): bool
    {
        $available = $this->availableForMonth($bucket, $year, $month);
        $need = $this->obligationForMonth($bucket, $year, $month)
            + $this->rolledForwardObligation($bucket, $year, $month);

        return $available < $need;
    }

    /**
     * Convenience: list all non-archived buckets that are underfunded in
     * (year, month). Returns Bucket models; UI decides how to render.
     *
     * @return Collection<int, Bucket>
     */
    public function underfundedBuckets(int $year, int $month)
    {
        return Bucket::query()
            ->where('archived', false)
            ->where('kind', '!=', 'system')
            ->get()
            ->filter(fn (Bucket $b) => $this->isUnderfunded($b, $year, $month))
            ->values();
    }
}
