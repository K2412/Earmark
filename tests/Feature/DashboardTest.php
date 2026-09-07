<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the overview', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Overview')
            ->has('unassignedAvailable')
            ->has('underfundedBuckets')
            ->has('netWorth.investable')
            ->has('netWorth.total')
            ->has('netWorth.freshness')
        );
});
