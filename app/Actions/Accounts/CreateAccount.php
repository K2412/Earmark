<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\Household;
use App\Services\Account\AccountService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateAccount
{
    use AsAction;

    public function __construct(private AccountService $accounts) {}

    /**
     * @param  array{name: string, type: string, starting_balance: int, starting_balance_date: string}  $data
     */
    public function handle(array $data, Household $household): Account
    {
        return $this->accounts->create($data, $household);
    }
}
