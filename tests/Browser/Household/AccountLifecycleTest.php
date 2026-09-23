<?php

use App\Models\Account;

it('creates a Canadian registered account', function () {
    actingAsOwner();

    visit('/household/accounts')
        ->click('@open-create-account')
        ->fill('name', 'My TFSA')
        ->select('type', 'tfsa')
        ->fill('starting_balance', '1500000')
        ->fill('starting_balance_date', '2026-01-02')
        ->click('@submit-create-account')
        ->assertSee('My TFSA')
        ->assertSee('TFSA')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('accounts', ['name' => 'My TFSA', 'type' => 'tfsa', 'currency' => 'CAD']);
});

it('edits an account', function () {
    $user = actingAsOwner();
    Account::factory()->create(['household_id' => $user->household()->id, 'name' => 'Editable', 'type' => 'chequing']);

    visit('/household/accounts')
        ->assertSee('Editable')
        ->click('Edit')
        ->fill('edit-name', 'Edited Name')
        ->click('@save-account')
        ->assertSee('Edited Name')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('accounts', ['name' => 'Edited Name']);
});

it('archives an account out of the active list', function () {
    $user = actingAsOwner();
    Account::factory()->create(['household_id' => $user->household()->id, 'name' => 'Old Card', 'type' => 'credit_card']);

    visit('/household/accounts')
        ->assertSee('Old Card')
        ->click('Archive')
        ->assertSee('Archived accounts')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('accounts', ['name' => 'Old Card', 'archived' => true]);
});
