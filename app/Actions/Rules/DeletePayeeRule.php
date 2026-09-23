<?php

namespace App\Actions\Rules;

use App\Models\PayeeRule;
use App\Services\Payee\PayeeRuleService;
use Lorisleiva\Actions\Concerns\AsAction;

class DeletePayeeRule
{
    use AsAction;

    public function __construct(private PayeeRuleService $rules) {}

    public function handle(PayeeRule $rule): void
    {
        $this->rules->delete($rule);
    }
}
