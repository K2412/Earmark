<?php

use App\Models\Account;

it('imports an OFX bank export into staging and reaches review', function () {
    $user = actingAsOwner();
    $account = Account::factory()->create([
        'household_id' => $user->household()->id,
        'name' => 'Daily Chequing',
    ]);

    visit('/household/import')
        ->select('@import-account', $account->id)
        ->attach('@import-file', base_path('tests/Fixtures/statements/sample-statement.ofx'))
        ->assertSee('Structured bank export (OFX) detected')
        ->click('@import-submit')
        ->assertSee('Review import')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'LOBLAWS #5025',
        'amount' => -7250,
        'external_id' => '2026060101',
    ]);

    // Staging never writes ledger rows.
    $this->assertDatabaseCount('transactions', 0);
});
