<?php

use App\Models\Account;
use App\Models\Transaction;

it('creates, previews, and applies a payee rule', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'payee' => 'LOBLAWS #5025',
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ]);

    visit('/household/rules')
        ->click('@open-create-rule')
        ->fill('rule-pattern', 'loblaws')
        ->fill('rule-rename', 'Loblaws')
        ->click('@preview-rule')
        ->assertSee('LOBLAWS #5025')
        ->click('@submit-rule')
        ->assertSee('loblaws')
        ->click('@apply-rule')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', ['payee' => 'Loblaws']);
});
