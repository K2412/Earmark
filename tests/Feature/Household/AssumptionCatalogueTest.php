<?php

use App\Models\Assumption;
use App\Models\User;
use App\Services\Canadian\CanadianAssumptionCatalogue;
use Carbon\CarbonImmutable;

test('every system assumption is fully labelled', function () {
    $systemRows = Assumption::query()->whereNull('household_id')->get();

    expect($systemRows)->not->toBeEmpty();

    foreach ($systemRows as $row) {
        expect($row->jurisdiction)->not->toBeEmpty()
            ->and($row->effective_year)->toBeGreaterThan(0)
            ->and($row->unit)->not->toBeEmpty()
            ->and($row->source_url)->not->toBeNull()
            ->and($row->source_date)->not->toBeNull()
            ->and($row->catalogue_version)->not->toBeEmpty();
    }
});

test('a household override supersedes the system default', function () {
    $user = User::factory()->create();
    $household = $user->household();

    expect(app(CanadianAssumptionCatalogue::class)->value('tfsa_limit', 2026, $household))->toBe(700000);

    Assumption::factory()->create([
        'household_id' => $household->id, 'key' => 'tfsa_limit', 'value' => 999999, 'effective_year' => 2026,
    ]);

    expect(app(CanadianAssumptionCatalogue::class)->value('tfsa_limit', 2026, $household))->toBe(999999);
});

test('assumptions are effective-dated to the most recent applicable year', function () {
    $user = User::factory()->create();
    Assumption::factory()->create(['key' => 'rrsp_limit', 'value' => 3000000, 'effective_year' => 2025]);

    $catalogue = app(CanadianAssumptionCatalogue::class);

    expect($catalogue->value('rrsp_limit', 2025, $user->household()))->toBe(3000000)   // 2025 row
        ->and($catalogue->value('rrsp_limit', 2026, $user->household()))->toBe(3210000); // 2026 system row wins
});

test('a stale source is flagged', function () {
    $fresh = Assumption::factory()->make(['source_date' => now()->subMonths(2)->toDateString()]);
    $stale = Assumption::factory()->make(['source_date' => now()->subYears(2)->toDateString()]);

    $catalogue = app(CanadianAssumptionCatalogue::class);

    expect($catalogue->isStale($fresh, CarbonImmutable::now()))->toBeFalse()
        ->and($catalogue->isStale($stale, CarbonImmutable::now()))->toBeTrue();
});

test('a self-hoster can override an assumption via the endpoint without code changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('household.assumptions.store'), [
        'key' => 'inflation_rate', 'value' => 300, 'unit' => 'bps', 'effective_year' => 2026,
        'source_date' => '2026-05-01', 'source_url' => 'https://example.test/cra',
    ])->assertSessionHasNoErrors();

    expect(app(CanadianAssumptionCatalogue::class)->value('inflation_rate', 2026, $user->household()))->toBe(300);
});

test('one household override does not leak to another', function () {
    $mine = User::factory()->create();
    $other = User::factory()->create();
    Assumption::factory()->create(['household_id' => $other->household()->id, 'key' => 'tfsa_limit', 'value' => 111111, 'effective_year' => 2026]);

    // I still see the system default, not the other household's override.
    expect(app(CanadianAssumptionCatalogue::class)->value('tfsa_limit', 2026, $mine->household()))->toBe(700000);
});

test('the catalogue page renders resolved assumptions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('household.assumptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('household/Assumptions')->has('assumptions', 6));
});
