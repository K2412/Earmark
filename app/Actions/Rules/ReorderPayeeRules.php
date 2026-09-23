<?php

namespace App\Actions\Rules;

use App\Models\Household;
use App\Services\Payee\PayeeRuleService;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderPayeeRules
{
    use AsAction;

    public function __construct(private PayeeRuleService $rules) {}

    /**
     * @param  list<string>  $orderedIds
     */
    public function handle(Household $household, array $orderedIds): void
    {
        $this->rules->reorder($household, $orderedIds);
    }
}
