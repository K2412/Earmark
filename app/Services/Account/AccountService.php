<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Household;
use Illuminate\Support\Collection;

class AccountService
{
    /**
     * @return Collection<int, Account>
     */
    public function listForHousehold(Household $household): Collection
    {
        return $household->accounts()->where('archived', false)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  array{name: string, type: string, starting_balance: int, starting_balance_date: string}  $data
     */
    public function create(array $data, Household $household): Account
    {
        return Account::query()->create([
            ...$data,
            'household_id' => $household->id,
        ]);
    }
}
