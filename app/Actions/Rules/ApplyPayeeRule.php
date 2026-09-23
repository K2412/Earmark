<?php

namespace App\Actions\Rules;

use App\Models\PayeeRule;
use App\Models\User;
use App\Services\Payee\PayeeRuleService;
use App\Services\Transaction\TransactionActivityLogger;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Explicitly applies a rule's actions to all existing matching transactions and
 * records one activity entry. Never splits or deletes; disabling a rule instead
 * of applying it leaves history untouched (task #1250).
 */
class ApplyPayeeRule
{
    use AsAction;

    public function __construct(
        private PayeeRuleService $rules,
        private TransactionActivityLogger $activity,
    ) {}

    public function handle(PayeeRule $rule, User $user): int
    {
        $household = $rule->household;
        $matches = $this->rules->matchingTransactions($household, $rule->pattern);

        DB::transaction(function () use ($rule, $matches): void {
            foreach ($matches as $transaction) {
                $this->rules->applyTo($rule, $transaction);
                $transaction->save();
            }
        });

        if ($matches->isNotEmpty()) {
            $this->activity->log(
                $household,
                $user,
                null,
                'rule_applied',
                sprintf("Applied rule '%s' to %d transaction(s)", $rule->label(), $matches->count()),
            );
        }

        return $matches->count();
    }
}
