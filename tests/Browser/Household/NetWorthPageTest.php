<?php

use App\Models\FinancialPosition;
use App\Models\HouseholdPlan;
use App\Models\Valuation;
use App\Support\Money;

it('adds a position then a plan and shows the projection table', function () {
    $this->travelTo('2026-09-07 12:00:00');

    actingAsOwner();

    visit('/household/net-worth')
        ->assertSee('Add first financial position')
        ->click('@open-create-position')
        ->fill('name', 'RRSP')
        ->select('classification', 'asset')
        ->select('purpose', 'investable')
        ->fill('amount', '1000000')
        ->click('@submit-create-position')
        ->assertSee('RRSP')
        ->assertSee('$10,000.00')
        ->fill('target_cents', '2000000')
        ->fill('target_year', '2028')
        ->fill('inflation_bps', '0')
        ->fill('return_bps', '700')
        ->fill('#phase-amount', '100000')
        ->click('@submit-save-plan')
        ->assertSee('Projection')
        ->assertSee('earmark.projection.v1');

    $this->assertDatabaseHas('financial_positions', [
        'name' => 'RRSP',
        'classification' => 'asset',
        'purpose' => 'investable',
    ]);
    $this->assertDatabaseHas('household_plans', [
        'target_cents' => 2_000_000,
        'target_year' => 2028,
    ]);
});

it('records a new valuation on a position', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();
    $position = FinancialPosition::factory()->create([
        'household_id' => $user->household()->id,
        'name' => 'RRSP',
    ]);
    Valuation::factory()->create([
        'financial_position_id' => $position->id,
        'amount' => 100_000,
        'valued_at' => '2026-01-01',
        'created_by_user_id' => $user->id,
    ]);

    visit('/household/net-worth')
        ->assertSee('$1,000.00')
        ->click('@open-create-valuation-'.$position->id)
        ->fill('#valuation-amount-'.$position->id, '250000')
        ->click('@submit-create-valuation-'.$position->id)
        ->assertSee('$2,500.00');

    $this->assertDatabaseHas('valuations', [
        'financial_position_id' => $position->id,
        'amount' => 250_000,
    ]);
});

it('keeps home-purchase assets out of investable', function () {
    $this->travelTo('2026-09-07 12:00:00');

    actingAsOwner();

    visit('/household/net-worth')
        ->click('@open-create-position')
        ->fill('name', 'Down payment')
        ->select('classification', 'asset')
        ->select('purpose', 'home_purchase')
        ->fill('amount', '80000')
        ->click('@submit-create-position')
        ->assertSee('Down payment')
        ->assertSee('$800.00');

    $this->assertDatabaseHas('financial_positions', [
        'name' => 'Down payment',
        'purpose' => 'home_purchase',
    ]);
});

it('keeps overview net worth cards in sync with a saved position', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $position = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'name' => 'TFSA',
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

    visit('/household/dashboard')
        ->assertSee(Money::format(250_000))
        ->assertSee(Money::format(750_000))
        ->assertSee('Open Net Worth');
});
