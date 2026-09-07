<?php

use App\Models\Account;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('accounts index renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('household/Accounts')->has('accounts'));
});

test('accounts index lists household accounts only', function () {
    $user = User::factory()->create();
    $household = $user->household();

    Account::factory()->create(['household_id' => $household->id, 'name' => 'Daily Chequing']);
    Account::factory()->create(['household_id' => $household->id, 'name' => 'Emergency Savings']);
    Account::factory()->create(['name' => 'Other House Account']);

    $this->actingAs($user)
        ->get(route('household.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Accounts')
            ->has('accounts', 2)
            ->where('accounts.0.name', 'Daily Chequing')
            ->where('accounts.1.name', 'Emergency Savings')
        );
});

test('create account persists for the household', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.accounts.store'), [
            'name' => 'New Chequing',
            'type' => 'chequing',
            'starting_balance' => 50000,
            'starting_balance_date' => '2026-06-23',
        ])
        ->assertRedirect(route('household.accounts.index'));

    $this->assertDatabaseHas('accounts', [
        'household_id' => $user->household()->id,
        'name' => 'New Chequing',
        'type' => 'chequing',
        'starting_balance' => 50000,
    ]);
});

test('create account fails validation for missing name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.accounts.index'))
        ->post(route('household.accounts.store'), [
            'name' => '',
            'type' => 'chequing',
            'starting_balance' => 0,
            'starting_balance_date' => '2026-06-23',
        ])
        ->assertRedirect(route('household.accounts.index'))
        ->assertSessionHasErrors(['name']);
});

test('create account fails validation for invalid date format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.accounts.index'))
        ->post(route('household.accounts.store'), [
            'name' => 'X',
            'type' => 'chequing',
            'starting_balance' => 0,
            'starting_balance_date' => '2026/06/23',
        ])
        ->assertSessionHasErrors(['starting_balance_date']);
});

test('guests are redirected from the accounts page', function () {
    $this->get(route('household.accounts.index'))
        ->assertRedirect(route('login'));
});
