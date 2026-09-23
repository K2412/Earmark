<?php

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Models\FinancialPosition;
use App\Models\User;
use App\Models\Valuation;
use App\Services\NetWorth\SnapshotService;
use Carbon\CarbonImmutable;

function investablePosition(User $user, PositionPurpose $purpose = PositionPurpose::Investable, ?string $ownerId = null): FinancialPosition
{
    return FinancialPosition::factory()->create([
        'household_id' => $user->household()->id,
        'classification' => PositionClassification::Asset,
        'purpose' => $purpose,
        'owner_user_id' => $ownerId,
    ]);
}

test('a snapshot reconciles to the selected date', function () {
    $user = User::factory()->create();
    $position = investablePosition($user);
    Valuation::factory()->create(['financial_position_id' => $position->id, 'amount' => 10000, 'valued_at' => '2026-01-15']);
    Valuation::factory()->create(['financial_position_id' => $position->id, 'amount' => 20000, 'valued_at' => '2026-06-15']);

    $service = app(SnapshotService::class);

    expect($service->forHousehold($user->household(), CarbonImmutable::parse('2026-03-01'))['investable_cents'])->toBe(10000)
        ->and($service->forHousehold($user->household(), CarbonImmutable::parse('2026-07-01'))['investable_cents'])->toBe(20000);
});

test('archiving a valuation falls back to the prior one without deleting it', function () {
    $user = User::factory()->create();
    $position = investablePosition($user);
    Valuation::factory()->create(['financial_position_id' => $position->id, 'amount' => 10000, 'valued_at' => '2026-01-15']);
    $recent = Valuation::factory()->create(['financial_position_id' => $position->id, 'amount' => 20000, 'valued_at' => '2026-06-15']);

    $this->actingAs($user)
        ->post(route('household.net-worth.valuations.archive', $recent))
        ->assertSessionHasNoErrors();

    expect(app(SnapshotService::class)->forHousehold($user->household(), CarbonImmutable::parse('2026-07-01'))['investable_cents'])->toBe(10000);
    $this->assertDatabaseHas('valuations', ['id' => $recent->id, 'archived' => true]); // retained, not destroyed
});

test('a valuation can be corrected', function () {
    $user = User::factory()->create();
    $position = investablePosition($user);
    $valuation = Valuation::factory()->create(['financial_position_id' => $position->id, 'amount' => 10000, 'valued_at' => '2026-06-15']);

    $this->actingAs($user)
        ->patch(route('household.net-worth.valuations.update', $valuation), ['amount' => 12345, 'valued_at' => '2026-06-15'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('valuations', ['id' => $valuation->id, 'amount' => 12345]);
});

test('trends can be filtered by owner and purpose', function () {
    $user = User::factory()->create();
    $mine = investablePosition($user, ownerId: $user->id);
    $joint = investablePosition($user, ownerId: null);
    Valuation::factory()->create(['financial_position_id' => $mine->id, 'amount' => 5000, 'valued_at' => '2026-01-01']);
    Valuation::factory()->create(['financial_position_id' => $joint->id, 'amount' => 9000, 'valued_at' => '2026-01-01']);

    $service = app(SnapshotService::class);

    expect($service->forHousehold($user->household(), null, ['owner_user_id' => (string) $user->id])['investable_cents'])->toBe(5000)
        ->and($service->forHousehold($user->household(), null)['investable_cents'])->toBe(14000);
});

test('primary residence and home-purchase assets never seed investable', function () {
    $user = User::factory()->create();
    $home = investablePosition($user, PositionPurpose::PrimaryResidence);
    $fund = investablePosition($user, PositionPurpose::HomePurchase);
    Valuation::factory()->create(['financial_position_id' => $home->id, 'amount' => 500000, 'valued_at' => '2026-01-01']);
    Valuation::factory()->create(['financial_position_id' => $fund->id, 'amount' => 50000, 'valued_at' => '2026-01-01']);

    $snapshot = app(SnapshotService::class)->forHousehold($user->household());

    expect($snapshot['investable_cents'])->toBe(0)
        ->and($snapshot['home_equity_cents'])->toBe(500000)
        ->and($snapshot['home_purchase_cents'])->toBe(50000);
});

test('history returns a twelve-point month-end series', function () {
    $user = User::factory()->create();
    $position = investablePosition($user);
    Valuation::factory()->create(['financial_position_id' => $position->id, 'amount' => 10000, 'valued_at' => now()->subMonths(6)->toDateString()]);

    $history = app(SnapshotService::class)->history($user->household(), 12);

    expect($history)->toHaveCount(12)
        ->and($history[11]['investable_cents'])->toBe(10000); // most recent point sees the valuation
});

test('a user cannot edit another household valuation', function () {
    $user = User::factory()->create();
    $other = Valuation::factory()->create([
        'financial_position_id' => FinancialPosition::factory()->create()->id,
    ]);

    $this->actingAs($user)
        ->patch(route('household.net-worth.valuations.update', $other), ['amount' => 1, 'valued_at' => '2026-01-01'])
        ->assertNotFound();
});
