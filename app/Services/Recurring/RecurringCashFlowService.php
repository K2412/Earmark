<?php

namespace App\Services\Recurring;

use App\Models\Household;
use App\Models\RecurringSchedule;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Infers recurring candidates from reviewed history and reads confirmed
 * schedules. Detection is explainable (frequency + expected range + evidence)
 * and never an automatic commitment — a candidate becomes real only when
 * confirmed. A persisted schedule of any status suppresses re-detection of that
 * payee (task #1253).
 */
class RecurringCashFlowService
{
    /**
     * @return list<array{name: string, frequency: string, amount_min: int, amount_max: int, occurrences: int, last_date: string, evidence_ids: list<string>}>
     */
    public function detectCandidates(Household $household): array
    {
        $suppressed = RecurringSchedule::query()
            ->where('household_id', $household->id)
            ->pluck('name')
            ->map(fn (string $name): string => mb_strtolower(trim($name)))
            ->all();

        $grouped = Transaction::query()
            ->where('household_id', $household->id)
            ->whereNull('transfer_pair_id')
            ->where('reviewed', true)
            ->orderBy('date')
            ->get()
            ->groupBy(fn (Transaction $t): string => mb_strtolower(trim($t->payee)));

        $candidates = [];

        foreach ($grouped as $key => $transactions) {
            if (in_array($key, $suppressed, true) || $transactions->count() < 3) {
                continue;
            }

            $dates = $transactions->pluck('date')->values();
            $gaps = [];

            for ($i = 1; $i < $dates->count(); $i++) {
                $gaps[] = abs((float) $dates[$i - 1]->diffInDays($dates[$i]));
            }

            $frequency = $this->classify($this->median($gaps));

            if ($frequency === null) {
                continue;
            }

            $amounts = $transactions->pluck('amount');

            $candidates[] = [
                'name' => $transactions->first()->payee,
                'frequency' => $frequency,
                'amount_min' => (int) $amounts->min(),
                'amount_max' => (int) $amounts->max(),
                'occurrences' => $transactions->count(),
                'last_date' => $dates->last()->toDateString(),
                'evidence_ids' => $transactions->pluck('id')->all(),
            ];
        }

        return $candidates;
    }

    public function classify(float $days): ?string
    {
        return match (true) {
            $days >= 5 && $days <= 9 => 'weekly',
            $days >= 12 && $days <= 16 => 'biweekly',
            $days >= 26 && $days <= 34 => 'monthly',
            $days >= 85 && $days <= 95 => 'quarterly',
            $days >= 350 && $days <= 380 => 'yearly',
            default => null,
        };
    }

    /**
     * @return Collection<int, RecurringSchedule>
     */
    public function upcoming(Household $household): Collection
    {
        return RecurringSchedule::query()
            ->where('household_id', $household->id)
            ->where('status', 'confirmed')
            ->whereNotNull('next_due_date')
            ->orderBy('next_due_date')
            ->get();
    }

    /**
     * @param  list<float>  $numbers
     */
    private function median(array $numbers): float
    {
        if ($numbers === []) {
            return 0.0;
        }

        sort($numbers);
        $count = count($numbers);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($numbers[$middle - 1] + $numbers[$middle]) / 2
            : $numbers[$middle];
    }
}
