<?php

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Models\FinancialPosition;
use App\Models\Household;
use App\Models\User;
use App\Models\Valuation;
use App\Services\NetWorth\SnapshotService;
use Carbon\CarbonImmutable;

test('snapshot separates investable, home purchase, home equity, and total net worth', function () {
    $user = User::factory()->create();
    $household = $user->household();

    $investable = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'classification' => PositionClassification::Asset,
        'purpose' => PositionPurpose::Investable,
        'name' => 'RRSP',
    ]);
    $homePurchase = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'classification' => PositionClassification::Asset,
        'purpose' => PositionPurpose::HomePurchase,
        'name' => 'Down payment',
    ]);
    $home = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'classification' => PositionClassification::Asset,
        'purpose' => PositionPurpose::PrimaryResidence,
        'name' => 'House',
    ]);
    $mortgage = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'classification' => PositionClassification::Liability,
        'purpose' => PositionPurpose::PrimaryResidence,
        'name' => 'Mortgage',
    ]);

    Valuation::factory()->create(['financial_position_id' => $investable->id, 'amount' => 500_000, 'valued_at' => '2026-09-01', 'created_by_user_id' => $user->id]);
    Valuation::factory()->create(['financial_position_id' => $homePurchase->id, 'amount' => 80_000, 'valued_at' => '2026-09-01', 'created_by_user_id' => $user->id]);
    Valuation::factory()->create(['financial_position_id' => $home->id, 'amount' => 2_000_000, 'valued_at' => '2026-09-01', 'created_by_user_id' => $user->id]);
    Valuation::factory()->create(['financial_position_id' => $mortgage->id, 'amount' => 1_200_000, 'valued_at' => '2026-09-01', 'created_by_user_id' => $user->id]);

    $snapshot = (new SnapshotService)->forHousehold($household, CarbonImmutable::parse('2026-09-07'));

    expect($snapshot['investable_cents'])->toBe(500_000)
        ->and($snapshot['home_purchase_cents'])->toBe(80_000)
        ->and($snapshot['home_equity_cents'])->toBe(800_000)
        ->and($snapshot['total_net_worth_cents'])->toBe(1_380_000)
        ->and($snapshot['has_positions'])->toBeTrue();
});

test('snapshot uses the latest valuation and counts stale ones', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $position = FinancialPosition::factory()->create([
        'household_id' => $household->id,
        'purpose' => PositionPurpose::Investable,
    ]);

    Valuation::factory()->create([
        'financial_position_id' => $position->id,
        'amount' => 100,
        'valued_at' => '2025-01-01',
        'created_by_user_id' => $user->id,
    ]);
    Valuation::factory()->create([
        'financial_position_id' => $position->id,
        'amount' => 250_000,
        'valued_at' => '2026-01-01',
        'created_by_user_id' => $user->id,
    ]);

    $snapshot = (new SnapshotService)->forHousehold($household, CarbonImmutable::parse('2026-09-07'));

    expect($snapshot['investable_cents'])->toBe(250_000)
        ->and($snapshot['freshness']['stale_count'])->toBe(1)
        ->and($snapshot['freshness']['status'])->toBe('stale')
        ->and($snapshot['freshness']['oldest_valued_at'])->toBe('2026-01-01')
        ->and($snapshot['freshness']['newest_valued_at'])->toBe('2026-01-01');
});

test('empty household has no positions and unknown freshness', function () {
    $household = Household::factory()->create();

    $snapshot = (new SnapshotService)->forHousehold($household);

    expect($snapshot['has_positions'])->toBeFalse()
        ->and($snapshot['investable_cents'])->toBe(0)
        ->and($snapshot['freshness']['status'])->toBe('unknown');
});
