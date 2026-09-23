<?php

use App\Models\Account;

it('imports a CSV statement into staging without touching the ledger', function () {
    $user = actingAsOwner();
    $account = Account::factory()->create([
        'household_id' => $user->household()->id,
        'name' => 'Daily Chequing',
    ]);

    visit('/household/import')
        ->select('@import-account', $account->id)
        ->attach('@import-file', base_path('tests/Fixtures/statements/sample-statement.csv'))
        ->assertSee('LOBLAWS #5025')
        ->assertSee('-$72.50')
        ->assertSee('$2,000.00')
        ->click('@import-submit')
        ->assertPathIs('/household/import')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'LOBLAWS #5025',
        'amount' => -7250,
    ]);
    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'PAYROLL DEPOSIT',
        'amount' => 200000,
    ]);

    // Staging never writes ledger rows.
    $this->assertDatabaseCount('transactions', 0);
});

it('reaches the import page from the transactions page', function () {
    actingAsOwner();

    visit('/household/transactions')
        ->click('Import CSV')
        ->assertPathIs('/household/import')
        ->assertSee('Map your statement columns');
});
