<?php

namespace App\Services\Payee;

use App\Models\PayeeRule;

class PayeeRuleService
{
    /**
     * Suggest a category_id + bucket_id for the given payee by walking the
     * rule set in priority order (lowest priority number wins) and returning
     * the first rule that matches.
     *
     * @return array{rule: ?PayeeRule, category_id: ?string, bucket_id: ?string}
     */
    public function suggest(string $payee): array
    {
        if ($payee === '') {
            return ['rule' => null, 'category_id' => null, 'bucket_id' => null];
        }

        $rules = PayeeRule::query()
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();

        foreach ($rules as $rule) {
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
     * Same as suggest() but only returns matches whose auto_apply=true.
     *
     * @return array{rule: ?PayeeRule, category_id: ?string, bucket_id: ?string}
     */
    public function autoApply(string $payee): array
    {
        $match = $this->suggest($payee);

        if ($match['rule'] !== null && ! $match['rule']->auto_apply) {
            return ['rule' => null, 'category_id' => null, 'bucket_id' => null];
        }

        return $match;
    }
}
