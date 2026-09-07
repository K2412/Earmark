<?php

namespace App\Services\Payee;

use App\Models\Household;
use App\Models\PayeeRule;

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
}
