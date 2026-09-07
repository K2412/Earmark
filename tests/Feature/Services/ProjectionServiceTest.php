<?php

use App\Models\HouseholdPlan;
use App\Services\NetWorth\ProjectionService;

test('applyBps rounds half away from zero with integer cents', function () {
    $service = new ProjectionService;

    expect($service->applyBps(1_000_000, 700))->toBe(70_000)
        ->and($service->applyBps(1, 700))->toBe(0)
        ->and($service->applyBps(8, 700))->toBe(1)
        ->and($service->applyBps(-1_000_000, 700))->toBe(-70_000);
});

test('projects end-of-year contributions after return', function () {
    $plan = HouseholdPlan::factory()->create([
        'target_cents' => 2_000_000,
        'target_year' => 2028,
        'inflation_bps' => 0,
        'return_bps' => 700,
        'windfall_cents' => 0,
        'windfall_year' => null,
    ]);
    $plan->contributionPhases()->create([
        'start_year' => 2026,
        'end_year' => 2028,
        'annual_contribution_cents' => 100_000,
        'sort_order' => 0,
    ]);
    $plan->load('contributionPhases');

    $result = (new ProjectionService)->project(1_000_000, $plan, 2026);

    expect($result['formula_version'])->toBe(ProjectionService::FORMULA_VERSION)
        ->and($result['labels']['values'])->toBe('before tax')
        ->and($result['labels']['return'])->toBe('after fees')
        ->and(collect($result['series']['base'])->pluck('cents', 'year')->all())->toBe([
            2026 => 1_000_000,
            2027 => 1_170_000,
            2028 => 1_351_900,
        ]);
});

test('applies a windfall in its year after return and contribution', function () {
    $plan = HouseholdPlan::factory()->create([
        'target_cents' => 2_000_000,
        'target_year' => 2027,
        'inflation_bps' => 0,
        'return_bps' => 700,
        'windfall_cents' => 50_000,
        'windfall_year' => 2027,
    ]);
    $plan->contributionPhases()->create([
        'start_year' => 2027,
        'end_year' => 2027,
        'annual_contribution_cents' => 100_000,
        'sort_order' => 0,
    ]);
    $plan->load('contributionPhases');

    $result = (new ProjectionService)->project(1_000_000, $plan, 2026);

    expect(collect($result['series']['base'])->pluck('cents', 'year')->all())->toBe([
        2026 => 1_000_000,
        2027 => 1_220_000,
    ]);
});

test('sensitivity series use 5 7 and 9 percent independently of the plan return', function () {
    $plan = HouseholdPlan::factory()->create([
        'target_cents' => 1_000_000,
        'target_year' => 2027,
        'inflation_bps' => 0,
        'return_bps' => 400,
        'windfall_cents' => 0,
        'windfall_year' => null,
    ]);
    $plan->load('contributionPhases');

    $result = (new ProjectionService)->project(1_000_000, $plan, 2026);
    $service = new ProjectionService;

    expect($result['series']['base'][1]['cents'])->toBe(1_000_000 + $service->applyBps(1_000_000, 400))
        ->and($result['series']['bps_500'][1]['cents'])->toBe(1_050_000)
        ->and($result['series']['bps_700'][1]['cents'])->toBe(1_070_000)
        ->and($result['series']['bps_900'][1]['cents'])->toBe(1_090_000);
});

test('inflates the target with the same annual rounding rule', function () {
    $service = new ProjectionService;

    expect($service->inflate(1_000_000, 200, 2))->toBe(1_040_400);
});
