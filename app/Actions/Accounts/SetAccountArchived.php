<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Services\Account\AccountService;
use Lorisleiva\Actions\Concerns\AsAction;

class SetAccountArchived
{
    use AsAction;

    public function __construct(private AccountService $accounts) {}

    public function handle(Account $account, bool $archived): Account
    {
        return $this->accounts->setArchived($account, $archived);
    }
}
