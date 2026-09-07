<?php

use App\Models\Bucket;

it('shows unassigned funds, underfunded buckets, and net worth cards', function () {
    actingAsOwner();

    visit('/household/dashboard')
        ->assertSee('Unassigned Funds')
        ->assertSee('Underfunded buckets')
        ->assertSee('Every bucket has enough for this month')
        ->assertSee('Investable assets')
        ->assertSee('Total net worth')
        ->assertSee('Target gap')
        ->assertSee('Add first financial position');
});

it('lists underfunded buckets and opens the plan from overview', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();

    Bucket::factory()->create([
        'household_id' => $user->household()->id,
        'name' => 'Rent',
        'kind' => 'ongoing',
        'monthly_obligation' => 150000,
    ]);

    visit('/household/dashboard')
        ->assertSee('Rent')
        ->assertSee('Needs more this month')
        ->click('Open Plan')
        ->assertPathIs('/household/plan')
        ->assertSee('Underfunded');
});

it('opens accounts from the overview shortcuts', function () {
    actingAsOwner();

    visit('/household/dashboard')
        ->click('Accounts')
        ->assertPathIs('/household/accounts')
        ->assertSee('No accounts yet');
});
