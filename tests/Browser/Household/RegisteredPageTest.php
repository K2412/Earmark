<?php

use App\Models\Account;

it('records a contribution against a registered account', function () {
    $user = actingAsOwner();
    Account::factory()->create(['household_id' => $user->household()->id, 'type' => 'rrsp', 'name' => 'My RRSP']);

    visit('/household/registered')
        ->assertSee('My RRSP')
        ->click('@open-record-event')
        ->select('account_id', Account::query()->where('type', 'rrsp')->value('id'))
        ->fill('ev-amount', '250000')
        ->click('@submit-event')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('registered_account_events', ['type' => 'contribution', 'amount' => 250000]);
});
