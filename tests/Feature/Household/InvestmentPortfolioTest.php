<?php

use App\Models\Holding;
use App\Models\User;
use App\Services\Investment\InvestmentPortfolioService;

test('performance derives gain and simple return from entered values', function () {
    $user = User::factory()->create();
    $household = $user->household();
    Holding::factory()->create(['household_id' => $household->id, 'cost_basis_cents' => 100000, 'market_value_cents' => 150000]);
    Holding::factory()->create(['household_id' => $household->id, 'cost_basis_cents' => 200000, 'market_value_cents' => 200000]);

    $performance = app(InvestmentPortfolioService::class)->performance($household);

    expect($performance['total_cost_cents'])->toBe(300000)
        ->and($performance['total_market_cents'])->toBe(350000)
        ->and($performance['gain_cents'])->toBe(50000)
        ->and($performance['return_bps'])->toBe(1667); // 50,000 / 300,000
});

test('allocation reconciles to market values and sums to 100%', function () {
    $user = User::factory()->create();
    $household = $user->household();
    Holding::factory()->create(['household_id' => $household->id, 'asset_class' => 'equity', 'market_value_cents' => 600000, 'target_allocation_bps' => 6000]);
    Holding::factory()->create(['household_id' => $household->id, 'asset_class' => 'fixed_income', 'market_value_cents' => 400000, 'target_allocation_bps' => 4000]);

    $allocation = collect(app(InvestmentPortfolioService::class)->allocation($household));

    expect((int) $allocation->sum('actual_bps'))->toBe(10000)
        ->and($allocation->firstWhere('asset_class', 'equity')['actual_bps'])->toBe(6000)
        ->and($allocation->firstWhere('asset_class', 'equity')['target_bps'])->toBe(6000)
        ->and((int) $allocation->sum('market_cents'))->toBe(1000000);
});

test('a holding can be entered without a brokerage connection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('household.holdings.store'), [
        'name' => 'VEQT', 'symbol' => 'VEQT', 'asset_class' => 'equity',
        'cost_basis_cents' => 1000000, 'market_value_cents' => 1120000, 'target_allocation_bps' => 8000,
    ])->assertRedirect(route('household.holdings.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('holdings', ['name' => 'VEQT', 'market_value_cents' => 1120000]);
});

test('the holdings page states its performance methodology', function () {
    $user = User::factory()->create();
    Holding::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)->get(route('household.holdings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('household/Holdings')
            ->has('holdings', 1)
            ->where('methodology', InvestmentPortfolioService::METHODOLOGY)
        );
});

test('a user cannot update another household holding', function () {
    $user = User::factory()->create();
    $other = Holding::factory()->create();

    $this->actingAs($user)->patch(route('household.holdings.update', $other), [
        'name' => 'x', 'asset_class' => 'equity', 'cost_basis_cents' => 1, 'market_value_cents' => 1,
    ])->assertForbidden();
});
