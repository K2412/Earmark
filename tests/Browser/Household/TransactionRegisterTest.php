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
        ->click('@edit-row')
        ->fill('edit-payee', 'New Payee')
        ->click('@save-edit')
        ->assertSee('New Payee')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', ['payee' => 'New Payee']);
    $this->assertDatabaseHas('transaction_activities', ['action' => 'updated']);
});

it('creates a category inline from the edit modal', function () {
    $user = actingAsOwner();
    browserTransaction($user, ['payee' => 'Paycheck', 'amount' => 500000]);

    visit('/household/transactions')
        ->click('@edit-row')
        ->assertSee('Edit transaction')
        ->click('@edit-new-category')
        ->assertSee('Create a category')
        ->fill('@new-category-name', 'Salary')
        ->select('@new-category-type', 'income')
        ->click('@new-category-save')
        ->assertDontSee('Create a category')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('categories', [
        'household_id' => $user->household()->id,
        'name' => 'Salary',
        'type' => 'income',
    ]);
});

it('opens the edit panel from a populated register', function () {
    $user = actingAsOwner();

    // Edit opens a centered modal, so it must be reachable regardless of how many
    // rows precede the clicked one.
    foreach (range(1, 20) as $i) {
        browserTransaction($user, ['payee' => "Merchant {$i}", 'amount' => -100 * $i]);
    }

    visit('/household/transactions')
        ->click('@edit-row')
        ->assertSee('Edit transaction')
        ->fill('edit-payee', 'Edited From List')
        ->click('@save-edit')
        ->assertSee('Edited From List')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', ['payee' => 'Edited From List']);
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
