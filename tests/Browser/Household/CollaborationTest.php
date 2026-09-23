<?php

use App\Models\Account;
use App\Models\Transaction;

it('assigns a transaction to me for review from the register', function () {
    $user = actingAsOwner();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);
    Transaction::factory()->create([
        'household_id' => $user->household()->id,
        'account_id' => $account->id,
        'payee' => 'Costco',
        'reviewed' => false,
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ]);

    visit('/household/transactions')
        ->assertSee('Costco')
        ->click('@assign-to-me')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', ['payee' => 'Costco', 'review_assignee_id' => $user->id]);
});
