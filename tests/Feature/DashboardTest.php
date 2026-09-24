<?php

use App\Http\Controllers\Household\DashboardController;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
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
            ->has('cards.top_categories.items')
            ->has('cards.top_categories.total')
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

test('the overview ranks top spending categories this month', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $groceries = Category::factory()->create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'food']);
    $rent = Category::factory()->create(['household_id' => $household->id, 'name' => 'Rent', 'type' => 'housing']);

    Transaction::factory()->create(['household_id' => $household->id, 'account_id' => $account->id, 'category_id' => $groceries->id, 'amount' => -5000, 'date' => now()->toDateString(), 'created_by_user_id' => $user->id]);
    Transaction::factory()->create(['household_id' => $household->id, 'account_id' => $account->id, 'category_id' => $rent->id, 'amount' => -150000, 'date' => now()->toDateString(), 'created_by_user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cards.top_categories.items.0.name', 'Rent')
            ->where('cards.top_categories.items.0.spent', Money::format(150000))
            ->where('cards.top_categories.total', Money::format(155000))
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
