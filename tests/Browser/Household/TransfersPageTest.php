<?php

use App\Models\Account;
use App\Models\Transaction;

it('creates a transfer between two accounts', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();
    $household = $user->household();
    $checking = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Chequing',
    ]);
    $savings = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Savings',
    ]);

    visit('/household/transfers')
        ->click('@open-create-transfer')
        ->select('from_account_id', $checking->id)
        ->select('to_account_id', $savings->id)
        ->fill('amount', '50000')
        ->fill('memo', 'Monthly savings')
        ->click('@submit-create-transfer')
        ->assertSee('Chequing')
        ->assertSee('Savings')
        ->assertSee('$500.00');

    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'account_id' => $checking->id,
        'amount' => -50000,
        'memo' => 'Monthly savings',
    ]);
    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'account_id' => $savings->id,
        'amount' => 50000,
        'memo' => 'Monthly savings',
    ]);
});

it('deletes a transfer from the transfers screen', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();
    $household = $user->household();
    $checking = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Chequing',
    ]);
    $savings = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Savings',
    ]);

    $page = visit('/household/transfers');

    $page->click('@open-create-transfer')
        ->select('from_account_id', $checking->id)
        ->select('to_account_id', $savings->id)
        ->fill('amount', '25000')
        ->click('@submit-create-transfer')
        ->assertSee('$250.00');

    $outflow = Transaction::query()
        ->where('household_id', $household->id)
        ->whereNotNull('transfer_pair_id')
        ->where('amount', '<', 0)
        ->sole();

    $page->click('@destroy-transfer-'.$outflow->id)
        ->assertDontSee('$250.00');

    expect(Transaction::query()->where('household_id', $household->id)->count())->toBe(0);
});
