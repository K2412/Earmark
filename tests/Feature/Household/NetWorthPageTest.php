<?php

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Models\FinancialPosition;
use App\Models\HouseholdPlan;
use App\Models\User;
use App\Models\Valuation;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

test('net worth empty state has no projection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.net-worth.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/NetWorth')
            ->where('snapshot.has_positions', false)
            ->where('projection', null)
        );
});

test('adding a position then a plan produces a projection table', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.net-worth.positions.store'), [
            'name' => 'RRSP',
            'classification' => PositionClassification::Asset->value,
            'purpose' => PositionPurpose::Investable->value,
            'amount' => 1_000_000,
            'valued_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('household.net-worth.show'));

    $this->assertDatabaseHas('financial_positions', [
        'household_id' => $user->household()->id,
        'name' => 'RRSP',
    ]);

    $this->actingAs($user)
        ->post(route('household.net-worth.plan.store'), [
            'target_cents' => 2_000_000,
            'target_year' => now()->year + 2,
            'inflation_bps' => 0,
            'return_bps' => 700,
            'windfall_cents' => 0,
            'phases' => [[
                'start_year' => now()->year,
                'end_year' => now()->year + 2,
                'annual_contribution_cents' => 100_000,
            ]],
        ])
        ->assertRedirect(route('household.net-worth.show'));

    $this->actingAs($user)
        ->get(route('household.net-worth.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/NetWorth')
            ->where('snapshot.has_positions', true)
            ->where('snapshot.investable', Money::format(1_000_000))
            ->where('projection.formula_version', 'earmark.projection.v1')
            ->has('projection.table')
            ->has('projection.series.bps_500')
            ->has('projection.series.bps_700')
            ->has('projection.series.bps_900')
        );
});

test('overview cards stay consistent with net worth snapshot', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $position = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'purpose' => PositionPurpose::Investable,
        'classification' => PositionClassification::Asset,
    ]);
    Valuation::factory()->create([
        'financial_position_id' => $position->id,
        'amount' => 250_000,
        'valued_at' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);
    HouseholdPlan::factory()->create([
        'household_id' => $household->id,
        'target_cents' => 1_000_000,
        'target_year' => now()->year + 10,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('netWorth.investable', Money::format(250_000))
            ->where('netWorth.total', Money::format(250_000))
            ->where('netWorth.targetGap', Money::format(750_000))
        );
});

test('home purchase assets do not seed investable', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $homePurchase = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'purpose' => PositionPurpose::HomePurchase,
        'classification' => PositionClassification::Asset,
    ]);
    Valuation::factory()->create([
        'financial_position_id' => $homePurchase->id,
        'amount' => 80_000,
        'valued_at' => now()->toDateString(),
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('household.net-worth.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshot.investable', Money::format(0))
            ->where('snapshot.home_purchase', Money::format(80_000))
            ->where('snapshot.total_net_worth', Money::format(80_000))
        );
});

test('guests are redirected from net worth', function () {
    $this->get(route('household.net-worth.show'))
        ->assertRedirect(route('login'));
});
