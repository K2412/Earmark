<?php

namespace App\Actions\Accounts;

use App\Models\Household;
use App\Services\Account\AccountService;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderAccounts
{
    use AsAction;

    public function __construct(private AccountService $accounts) {}

    /**
     * @param  list<string>  $orderedIds
     */
    public function handle(Household $household, array $orderedIds): void
    {
        $this->accounts->reorder($household, $orderedIds);
    }
}
