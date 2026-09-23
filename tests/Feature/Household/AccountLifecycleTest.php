<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an account can be created with a Canadian type, owner, and institution', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.accounts.store'), [
            'name' => 'My TFSA',
            'institution' => 'Questrade',
            'type' => 'tfsa',
            'owner_user_id' => $user->id,
            'starting_balance' => 1500000,
            'starting_balance_date' => '2026-01-02',
        ])
        ->assertRedirect(route('household.accounts.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'household_id' => $user->household()->id,
        'name' => 'My TFSA',
        'institution' => 'Questrade',
        'type' => 'tfsa',
        'owner_user_id' => $user->id,
        'currency' => 'CAD',
    ]);
});

test('an unknown account type is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.accounts.index'))
        ->post(route('household.accounts.store'), [
            'name' => 'X',
            'type' => 'crypto_wallet',
            'starting_balance' => 0,
            'starting_balance_date' => '2026-01-02',
        ])
        ->assertSessionHasErrors('type');
});

test('an owner from outside the household is rejected', function () {
    $user = User::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.accounts.index'))
        ->post(route('household.accounts.store'), [
            'name' => 'Joint',
            'type' => 'chequing',
            'owner_user_id' => $outsider->id,
            'starting_balance' => 0,
            'starting_balance_date' => '2026-01-02',
        ])
        ->assertSessionHasErrors('owner_user_id');
});

test('editing an account keeps its transaction history', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id, 'name' => 'Old Name', 'type' => 'chequing']);
    $transaction = Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->from(route('household.accounts.index'))
        ->patch(route('household.accounts.update', $account), [
            'name' => 'Renamed Savings',
            'type' => 'savings',
            'starting_balance' => 25000,
            'starting_balance_date' => $account->starting_balance_date->toDateString(),
        ])
        ->assertRedirect(route('household.accounts.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Renamed Savings', 'type' => 'savings']);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'account_id' => $account->id]);
});

test('an account can be archived and restored', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id, 'archived' => false]);

    $this->actingAs($user)->post(route('household.accounts.archive', $account));
    $this->assertDatabaseHas('accounts', ['id' => $account->id, 'archived' => true]);

    // Archived accounts leave the active list but stay visible in the archived list.
    $this->actingAs($user)
        ->get(route('household.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts', 0)
            ->has('archivedAccounts', 1)
        );

    $this->actingAs($user)->post(route('household.accounts.restore', $account));
    $this->assertDatabaseHas('accounts', ['id' => $account->id, 'archived' => false]);
});

test('accounts can be reordered', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $first = Account::factory()->create(['household_id' => $household->id, 'sort_order' => 0]);
    $second = Account::factory()->create(['household_id' => $household->id, 'sort_order' => 1]);

    $this->actingAs($user)
        ->post(route('household.accounts.reorder'), ['ids' => [$second->id, $first->id]])
        ->assertSessionHasNoErrors();

    expect($first->refresh()->sort_order)->toBe(1)
        ->and($second->refresh()->sort_order)->toBe(0);
});

test('the index exposes account types, members, and archived accounts', function () {
    $user = User::factory()->create();
    Account::factory()->create(['household_id' => $user->household()->id, 'archived' => true]);

    $this->actingAs($user)
        ->get(route('household.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('types')
            ->has('members', 1)
            ->has('archivedAccounts', 1)
        );
});

test('a user cannot edit or archive another household account', function () {
    $user = User::factory()->create();
    $other = Account::factory()->create();

    $this->actingAs($user)->patch(route('household.accounts.update', $other), [
        'name' => 'x', 'type' => 'chequing', 'starting_balance' => 0, 'starting_balance_date' => '2026-01-02',
    ])->assertForbidden();

    $this->actingAs($user)->post(route('household.accounts.archive', $other))->assertNotFound();
});
