<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Services\Account\AccountService;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAccount
{
    use AsAction;

    public function __construct(private AccountService $accounts) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Account $account, array $data): Account
    {
        return $this->accounts->update($account, $data);
    }
}
