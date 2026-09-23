<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

/**
 * @param  array<string, mixed>  $attributes
 */
function browserTransaction(User $user, array $attributes = []): Transaction
{
    $household = $user->household();

    return Transaction::factory()->create(array_merge([
        'household_id' => $household->id,
        'account_id' => Account::factory()->create(['household_id' => $household->id, 'name' => 'Daily Chequing'])->id,
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ], $attributes));
}

it('edits a transaction from the register', function () {
    $user = actingAsOwner();
    browserTransaction($user, ['payee' => 'Old Payee', 'amount' => -1000]);

    visit('/household/transactions')
        ->assertSee('Old Payee')
        ->click('Edit')
        ->fill('edit-payee', 'New Payee')
        ->click('@save-edit')
        ->assertSee('New Payee')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', ['payee' => 'New Payee']);
    $this->assertDatabaseHas('transaction_activities', ['action' => 'updated']);
});

it('filters the register by payee', function () {
    $user = actingAsOwner();
    browserTransaction($user, ['payee' => 'Loblaws Store']);
    browserTransaction($user, ['payee' => 'Shell Fuel']);

    visit('/household/transactions')
        ->fill('f-payee', 'Loblaws')
        ->click('@apply-filters')
        ->assertSee('Loblaws Store')
        ->assertDontSee('Shell Fuel')
        ->assertNoJavaScriptErrors();
});

it('bulk marks transactions reviewed', function () {
    $user = actingAsOwner();
    browserTransaction($user, ['payee' => 'To Review', 'reviewed' => false]);

    visit('/household/transactions')
        ->assertSee('Needs review')
        ->click('@select-row')
        ->click('@bulk-reviewed')
        ->assertSee('Reviewed')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', ['payee' => 'To Review', 'reviewed' => true]);
});
