<?php

namespace App\Services\Account;

use App\Models\Account;

class AccountService
{
    /**
     * @param  array{name: string, type: string, starting_balance: int, starting_balance_date: string}  $data
     */
    public function create(array $data): Account
    {
        return Account::query()->create($data);
    }
}
