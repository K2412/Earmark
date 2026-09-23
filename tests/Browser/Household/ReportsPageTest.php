<?php

use App\Models\Account;
use App\Models\Transaction;

it('shows a cash-flow report with drilldown', function () {
    $user = actingAsOwner();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);
    Transaction::factory()->create([
        'household_id' => $user->household()->id,
        'account_id' => $account->id,
        'amount' => -3000,
        'payee' => 'Loblaws',
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ]);

    visit('/household/reports')
        ->assertSee('Reports')
        ->assertSee('Loblaws')
        ->assertNoJavaScriptErrors();
});
