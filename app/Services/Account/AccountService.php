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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Household $household): Account
    {
        return Account::query()->create([
            'currency' => 'CAD',
            ...$data,
            'household_id' => $household->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Account $account, array $data): Account
    {
        // Currency is fixed to CAD until multi-currency is a deliberate decision.
        unset($data['currency'], $data['household_id']);

        $account->fill($data)->save();

        return $account;
    }

    public function setArchived(Account $account, bool $archived): Account
    {
        $account->archived = $archived;
        $account->save();

        return $account;
    }

    /**
     * Apply a household-scoped order to accounts. Ignores ids from other households.
     *
     * @param  list<string>  $orderedIds
     */
    public function reorder(Household $household, array $orderedIds): void
    {
        $owned = $household->accounts()->pluck('id')->all();

        foreach ($orderedIds as $position => $id) {
            if (in_array($id, $owned, true)) {
                Account::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        }
    }
}
