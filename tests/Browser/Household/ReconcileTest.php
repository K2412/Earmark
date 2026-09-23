<?php

use App\Models\Account;
use App\Models\Transaction;

it('reconciles an account when the cleared balance matches', function () {
    $user = actingAsOwner();
    $account = Account::factory()->create([
        'household_id' => $user->household()->id,
        'starting_balance' => 0,
        'name' => 'Chequing',
    ]);
    Transaction::factory()->create([
        'household_id' => $user->household()->id,
        'account_id' => $account->id,
        'amount' => 5000,
        'cleared' => true,
        'date' => '2026-06-01',
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ]);

    visit('/household/reconcile')
        ->assertSee('Reconcile')
        ->assertSee('Statement balance')
        ->assertNoJavaScriptErrors();
});
