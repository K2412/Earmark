<?php

it('creates an account of the selected type', function (string $type, string $name) {
    $this->travelTo('2026-09-07 12:00:00');

    actingAsOwner();

    visit('/household/accounts')
        ->click('@open-create-account')
        ->fill('name', $name)
        ->select('type', $type)
        ->fill('starting_balance', '50000')
        ->click('@submit-create-account')
        ->assertSee($name)
        ->assertSee('$500.00');

    $this->assertDatabaseHas('accounts', [
        'name' => $name,
        'type' => $type,
        'starting_balance' => 50000,
    ]);
})->with([
    'chequing' => ['chequing', 'Daily Chequing'],
    'savings' => ['savings', 'Emergency Savings'],
    'credit card' => ['credit_card', 'Visa'],
]);
