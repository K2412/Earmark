<?php

use App\Http\Controllers\Household\DashboardController;
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
            ->has('availableCards', count(DashboardController::CARDS))
            ->has('enabledCards')
            ->has('cards.budget.unassigned')
            ->has('cards.budget.underfunded')
            ->has('cards.review_queue.count')
            ->has('cards.recurring.count')
            ->has('cards.goals.count')
            ->has('cards.net_worth.investable')
            ->has('cards.net_worth.total')
            ->has('cards.net_worth.freshness')
            ->has('cards.investments.total')
            ->has('cards.scenarios.count')
        );
});

test('every card is enabled by default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enabledCards', DashboardController::CARDS)
        );
});

test('members can choose which overview cards to show', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('dashboard.cards'), ['cards' => ['budget', 'goals']])
        ->assertRedirect();

    expect($user->household()->fresh()->overview_cards)->toBe(['budget', 'goals']);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enabledCards', ['budget', 'goals'])
        );
});

test('overview card preferences reject unknown cards', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('dashboard.cards'), ['cards' => ['budget', 'made_up_card']])
        ->assertSessionHasErrors('cards.1');

    expect($user->household()->fresh()->overview_cards)->toBeNull();
});
