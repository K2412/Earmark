<?php

use App\Actions\Households\CreateHousehold;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;

test('CreateHousehold creates a household and attaches the user as Owner', function () {
    $user = User::factory()->create();
    $existingHouseholdCount = $user->households()->count();

    $household = CreateHousehold::run($user, 'The Acme Household');

    expect($household)->toBeInstanceOf(Household::class)
        ->and($household->name)->toBe('The Acme Household')
        ->and($household->slug)->not->toBeEmpty()
        ->and($user->fresh()->households()->count())->toBe($existingHouseholdCount + 1)
        ->and($user->fresh()->ownsHousehold($household))->toBeTrue()
        ->and($user->fresh()->householdRole($household))->toBe(HouseholdRole::Owner);
});

test('CreateHousehold is invocable via run helper (AsAction smoke)', function () {
    $user = User::factory()->create();

    $household = CreateHousehold::run($user, 'Smoke Test Household');

    expect($household)->toBeInstanceOf(Household::class);
});
