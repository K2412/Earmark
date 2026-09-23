<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reconciliation\ReconciliationService;

/**
 * @param  array<string, mixed>  $attributes
 */
function reconTransaction(User $user, Account $account, array $attributes = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'household_id' => $user->household()->id,
        'account_id' => $account->id,
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ], $attributes));
}

test('calculated balance sums the starting balance and cleared rows up to the date', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id, 'starting_balance' => 10000]);

    reconTransaction($user, $account, ['amount' => -3000, 'cleared' => true, 'date' => '2026-06-01']);
    reconTransaction($user, $account, ['amount' => -2000, 'cleared' => false, 'date' => '2026-06-02']); // uncleared, ignored
    reconTransaction($user, $account, ['amount' => -9999, 'cleared' => true, 'date' => '2026-07-15']); // after cutoff

    $calculated = app(ReconciliationService::class)->calculatedBalance($account, '2026-06-30');

    expect($calculated)->toBe(7000);
});

test('reconciliation commits only when the balance matches', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id, 'starting_balance' => 10000]);
    $cleared = reconTransaction($user, $account, ['amount' => -3000, 'cleared' => true, 'date' => '2026-06-01']);

    // Off by 500 → refuses to commit.
    $this->actingAs($user)
        ->post(route('household.reconcile.store'), [
            'account_id' => $account->id,
            'statement_date' => '2026-06-30',
            'statement_balance' => 7500,
        ])
        ->assertRedirect(route('household.reconcile.index'));

    expect($cleared->refresh()->reconciled)->toBeFalse();
    $this->assertDatabaseCount('reconciliations', 0);

    // Exact match → commits and marks cleared rows reconciled.
    $this->actingAs($user)
        ->post(route('household.reconcile.store'), [
            'account_id' => $account->id,
            'statement_date' => '2026-06-30',
            'statement_balance' => 7000,
        ]);

    expect($cleared->refresh()->reconciled)->toBeTrue();
    $this->assertDatabaseHas('reconciliations', [
        'account_id' => $account->id,
        'status' => 'matched',
        'discrepancy_amount' => 0,
        'calculated_balance' => 7000,
    ]);
});

test('reconcile preview reports the discrepancy without committing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id, 'starting_balance' => 0]);
    reconTransaction($user, $account, ['amount' => 5000, 'cleared' => true, 'date' => '2026-06-01']);

    $this->actingAs($user)
        ->postJson(route('household.reconcile.preview'), [
            'account_id' => $account->id,
            'statement_date' => '2026-06-30',
            'statement_balance' => 4000,
        ])
        ->assertOk()
        ->assertJsonPath('calculated', 5000)
        ->assertJsonPath('discrepancy', -1000)
        ->assertJsonPath('matched', false);

    $this->assertDatabaseCount('reconciliations', 0);
});
