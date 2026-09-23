<?php

namespace App\Services\Payee;

use App\Models\Household;
use App\Models\PayeeRule;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class PayeeRuleService
{
    /**
     * @return array{rule: ?PayeeRule, category_id: ?string, bucket_id: ?string}
     */
    public function suggest(string $payee, ?Household $household = null): array
    {
        if ($payee === '') {
            return ['rule' => null, 'category_id' => null, 'bucket_id' => null];
        }

        $query = PayeeRule::query()
            ->where('enabled', true)
            ->orderBy('priority')
            ->orderBy('created_at');

        if ($household) {
            $query->where('household_id', $household->id);
        }

        foreach ($query->get() as $rule) {
            if ($rule->matches($payee)) {
                return [
                    'rule' => $rule,
                    'category_id' => $rule->category_id,
                    'bucket_id' => $rule->bucket_id,
                ];
            }
        }

        return ['rule' => null, 'category_id' => null, 'bucket_id' => null];
    }

    /**
     * @return array{rule: ?PayeeRule, category_id: ?string, bucket_id: ?string}
     */
    public function autoApply(string $payee, ?Household $household = null): array
    {
        $match = $this->suggest($payee, $household);

        if ($match['rule'] !== null && ! $match['rule']->auto_apply) {
            return ['rule' => null, 'category_id' => null, 'bucket_id' => null];
        }

        return $match;
    }

    /**
     * Existing non-transfer transactions whose payee matches the pattern.
     *
     * @return Collection<int, Transaction>
     */
    public function matchingTransactions(Household $household, string $pattern): Collection
    {
        if (trim($pattern) === '') {
            return collect();
        }

        return Transaction::query()
            ->where('household_id', $household->id)
            ->whereNull('transfer_pair_id')
            ->where('payee', 'like', '%'.$pattern.'%')
            ->with(['category', 'bucket'])
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Apply a rule's actions to a transaction in memory (no save). Rules never
     * split or delete; split transactions keep their per-split attribution.
     */
    public function applyTo(PayeeRule $rule, Transaction $transaction): void
    {
        if ($rule->rename_to) {
            $transaction->payee = $rule->rename_to;
        }

        if (! $transaction->is_split) {
            if ($rule->category_id) {
                $transaction->category_id = $rule->category_id;
            }

            if ($rule->bucket_id) {
                $transaction->bucket_id = $rule->bucket_id;
            }
        }

        if ($rule->hide_from_reports) {
            $transaction->excluded_from_reports = true;
        }

        if ($rule->mark_for_review) {
            $transaction->reviewed = false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Household $household): PayeeRule
    {
        return PayeeRule::query()->create([
            ...$data,
            'household_id' => $household->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PayeeRule $rule, array $data): PayeeRule
    {
        unset($data['household_id']);

        $rule->fill($data)->save();

        return $rule;
    }

    public function delete(PayeeRule $rule): void
    {
        $rule->delete();
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorder(Household $household, array $orderedIds): void
    {
        $owned = PayeeRule::query()->where('household_id', $household->id)->pluck('id')->all();

        foreach ($orderedIds as $position => $id) {
            if (in_array($id, $owned, true)) {
                PayeeRule::query()->whereKey($id)->update(['priority' => $position]);
            }
        }
    }
}
