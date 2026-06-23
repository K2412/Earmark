<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateAccount
{
    use AsAction;

    /**
     * @param  array{name: string, type: string, starting_balance: int, starting_balance_date: string}  $data
     */
    public function handle(array $data): Account
    {
        return Account::query()->create($data);
    }
}
