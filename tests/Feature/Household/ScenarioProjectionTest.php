<?php

use App\Models\Assumption;
use App\Models\Scenario;
use App\Models\User;
use App\Services\NetWorth\ScenarioService;

/**
 * @param  array<string, mixed>  $attributes
 */
function scenario(User $user, array $attributes = []): Scenario
{
    return Scenario::factory()->create(array_merge([
        'household_id' => $user->household()->id,
        'base_year' => 2026,
        'horizon_years' => 30,
        'starting_investable_cents' => 5000000,
        'annual_contribution_cents' => 1200000,
        'contribution_years' => 20,
        'return_bps' => 500,
        'inflation_bps' => 200,
    ], $attributes));
}

test('a projection is deterministic and reproducible from saved inputs', function () {
    $s = scenario(User::factory()->create());
    $service = app(ScenarioService::class);

    expect($service->project($s))->toBe($service->project($s))
        ->and($service->project($s)['formula_version'])->toBe(ScenarioService::FORMULA_VERSION);
});

test('sensitivity bands are ordered and today-dollar is below nominal', function () {
    $result = app(ScenarioService::class)->project(scenario(User::factory()->create()));

    expect($result['ending']['optimistic']['nominal'])->toBeGreaterThan($result['ending']['base']['nominal'])
        ->and($result['ending']['base']['nominal'])->toBeGreaterThan($result['ending']['conservative']['nominal'])
        ->and($result['ending']['base']['real'])->toBeLessThan($result['ending']['base']['nominal']);
});

test('drawdown reduces the ending balance', function () {
    $user = User::factory()->create();
    $without = app(ScenarioService::class)->project(scenario($user));
    $with = app(ScenarioService::class)->project(scenario($user, ['drawdown_start_year' => 2046, 'drawdown_annual_cents' => 3000000]));

    expect($with['ending']['base']['nominal'])->toBeLessThan($without['ending']['base']['nominal']);
});

test('home equity enters investable only through an explicit downsizing event', function () {
    $user = User::factory()->create();
    $without = app(ScenarioService::class)->project(scenario($user));
    $with = app(ScenarioService::class)->project(scenario($user, ['downsizing_year' => 2040, 'downsizing_proceeds_cents' => 40000000]));

    expect($with['ending']['base']['nominal'])->toBeGreaterThan($without['ending']['base']['nominal']);
});

test('storing a scenario snapshots the catalogue and stamps the formula version', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('household.scenarios.store'), [
        'name' => 'Base plan', 'base_year' => 2026, 'horizon_years' => 30,
        'starting_investable_cents' => 5000000, 'annual_contribution_cents' => 1200000,
        'contribution_years' => 20, 'return_bps' => 500, 'inflation_bps' => 200,
    ])->assertRedirect(route('household.scenarios.index'))->assertSessionHasNoErrors();

    $saved = Scenario::query()->where('household_id', $user->household()->id)->sole();
    expect($saved->formula_version)->toBe(ScenarioService::FORMULA_VERSION)
        ->and($saved->assumptions_snapshot)->toHaveKey('inflation_rate')
        ->and($saved->assumptions_snapshot['tfsa_limit'])->toBe(700000);
});

test('a later catalogue change does not rewrite a saved scenario', function () {
    $user = User::factory()->create();
    $s = scenario($user, ['inflation_bps' => 200]);
    $before = app(ScenarioService::class)->project($s);

    Assumption::factory()->create(['household_id' => $user->household()->id, 'key' => 'inflation_rate', 'value' => 900, 'effective_year' => 2026]);

    expect(app(ScenarioService::class)->project($s->fresh()))->toBe($before);
});

test('comparing two scenarios highlights the changed inputs', function () {
    $user = User::factory()->create();
    $a = scenario($user, ['name' => 'A', 'return_bps' => 500]);
    $b = scenario($user, ['name' => 'B', 'return_bps' => 700]);

    $this->actingAs($user)
        ->get(route('household.scenarios.index', ['a' => $a->id, 'b' => $b->id]))
        ->assertInertia(fn ($page) => $page
            ->component('household/Scenarios')
            ->where('comparison.changes.0.field', 'return_bps')
        );
});

test('a user cannot delete another household scenario', function () {
    $user = User::factory()->create();
    $other = Scenario::factory()->create();

    $this->actingAs($user)->delete(route('household.scenarios.destroy', $other))->assertNotFound();
});
