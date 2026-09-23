<?php

namespace App\Actions\Rules;

use App\Models\PayeeRule;
use App\Services\Payee\PayeeRuleService;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePayeeRule
{
    use AsAction;

    public function __construct(private PayeeRuleService $rules) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PayeeRule $rule, array $data): PayeeRule
    {
        return $this->rules->update($rule, $data);
    }
}
