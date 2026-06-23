<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the household dashboard to the login page', function () {
    $response = $this->get(route('household.dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the household dashboard without a team segment', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/household/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('currentTeam')
            ->missing('teams'),
        );

    expect(route('household.dashboard', absolute: false))->toBe('/household/dashboard');
});
