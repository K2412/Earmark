<?php

namespace App\Actions\Rules;

use App\Models\Household;
use App\Models\PayeeRule;
use App\Services\Payee\PayeeRuleService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreatePayeeRule
{
    use AsAction;

    public function __construct(private PayeeRuleService $rules) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Household $household): PayeeRule
    {
        return $this->rules->create($data, $household);
    }
}
