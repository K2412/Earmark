<?php

use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

test('accounts index renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.accounts.index'))
        ->assertOk()
        ->assertSeeText('Accounts');
});

test('accounts index lists existing accounts', function () {
    $user = User::factory()->create();
    Account::factory()->create(['name' => 'Daily Chequing']);
    Account::factory()->create(['name' => 'Emergency Savings']);

    $this->actingAs($user)
        ->get(route('household.accounts.index'))
        ->assertSeeText('Daily Chequing')
        ->assertSeeText('Emergency Savings');
});

test('create account via Livewire action persists and resets form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.accounts.index')
        ->set('form.name', 'New Chequing')
        ->set('form.type', 'chequing')
        ->set('form.starting_balance', 50000)
        ->set('form.starting_balance_date', '2026-06-23')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false)
        ->assertSet('form.name', '');

    $this->assertDatabaseHas('accounts', [
        'name' => 'New Chequing',
        'type' => 'chequing',
        'starting_balance' => 50000,
    ]);
});

test('create account fails validation for missing name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.accounts.index')
        ->set('form.name', '')
        ->set('form.starting_balance_date', '2026-06-23')
        ->call('save')
        ->assertHasErrors(['form.name']);
});

test('create account fails validation for invalid date format', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.accounts.index')
        ->set('form.name', 'X')
        ->set('form.starting_balance_date', '2026/06/23')
        ->call('save')
        ->assertHasErrors(['form.starting_balance_date']);
});

test('guests are redirected from the accounts page', function () {
    $this->get(route('household.accounts.index'))
        ->assertRedirect(route('login'));
});
