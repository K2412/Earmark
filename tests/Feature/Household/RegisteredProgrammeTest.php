<?php

use App\Models\Account;
use App\Models\RegisteredAccountEvent;
use App\Models\User;
use App\Services\Canadian\CanadianProgrammeService;

function registeredAccount(User $user, string $type = 'rrsp'): Account
{
    return Account::factory()->create(['household_id' => $user->household()->id, 'type' => $type]);
}

test('contributions sum by year, distinct from other events', function () {
    $user = User::factory()->create();
    $rrsp = registeredAccount($user);

    RegisteredAccountEvent::factory()->create(['account_id' => $rrsp->id, 'type' => 'contribution', 'amount' => 500000, 'plan_year' => 2026]);
    RegisteredAccountEvent::factory()->create(['account_id' => $rrsp->id, 'type' => 'contribution', 'amount' => 200000, 'plan_year' => 2026]);
    RegisteredAccountEvent::factory()->create(['account_id' => $rrsp->id, 'type' => 'contribution', 'amount' => 999999, 'plan_year' => 2025]);
    RegisteredAccountEvent::factory()->create(['account_id' => $rrsp->id, 'type' => 'hbp_withdrawal', 'amount' => 100000, 'plan_year' => 2026]);

    expect(app(CanadianProgrammeService::class)->contributions($rrsp, 2026))->toBe(700000);
});

test('HBP withdrawals, repayments, outstanding, and required repayment stay distinct', function () {
    $user = User::factory()->create();
    $rrsp = registeredAccount($user);

    RegisteredAccountEvent::factory()->create(['account_id' => $rrsp->id, 'type' => 'hbp_withdrawal', 'amount' => 3000000, 'plan_year' => 2024]);
    RegisteredAccountEvent::factory()->create(['account_id' => $rrsp->id, 'type' => 'hbp_repayment', 'amount' => 200000, 'plan_year' => 2026]);

    $service = app(CanadianProgrammeService::class);

    expect($service->hbpWithdrawn($rrsp))->toBe(3000000)
        ->and($service->hbpRepaid($rrsp))->toBe(200000)
        ->and($service->hbpOutstanding($rrsp))->toBe(2800000)
        ->and($service->hbpRequiredAnnualRepayment($rrsp))->toBe(200000); // 3,000,000 / 15
});

test('an authoritative room override supersedes the estimate with an as-of date', function () {
    $user = User::factory()->create();
    $tfsa = registeredAccount($user, 'tfsa');
    RegisteredAccountEvent::factory()->create(['account_id' => $tfsa->id, 'type' => 'contribution', 'amount' => 300000, 'plan_year' => 2026]);

    $service = app(CanadianProgrammeService::class);

    // Estimate = limit - contributions.
    $estimate = $service->room($tfsa, 700000, 2026);
    expect($estimate['amount'])->toBe(400000)->and($estimate['authoritative'])->toBeFalse();

    RegisteredAccountEvent::factory()->create([
        'account_id' => $tfsa->id, 'type' => 'room_override', 'amount' => 512300, 'plan_year' => 2026, 'as_of' => '2026-03-01',
    ]);

    $override = $service->room($tfsa, 700000, 2026);
    expect($override['amount'])->toBe(512300)
        ->and($override['authoritative'])->toBeTrue()
        ->and($override['as_of'])->toBe('2026-03-01');
});

test('spousal accounts keep individual room separate', function () {
    $user = User::factory()->create();
    $mine = registeredAccount($user, 'rrsp');
    $spouse = registeredAccount($user, 'rrsp');
    RegisteredAccountEvent::factory()->create(['account_id' => $mine->id, 'type' => 'contribution', 'amount' => 1000000, 'plan_year' => 2026]);

    $service = app(CanadianProgrammeService::class);

    expect($service->room($mine, 3210000, 2026)['amount'])->toBe(2210000)
        ->and($service->room($spouse, 3210000, 2026)['amount'])->toBe(3210000); // untouched, not combined
});

test('an event can be recorded and the page renders registered accounts', function () {
    $user = User::factory()->create();
    $rrsp = registeredAccount($user);

    $this->actingAs($user)->post(route('household.registered.store'), [
        'account_id' => $rrsp->id, 'type' => 'contribution', 'amount' => 250000,
        'occurred_on' => '2026-02-01', 'plan_year' => 2026,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('registered_account_events', ['account_id' => $rrsp->id, 'type' => 'contribution', 'amount' => 250000]);

    $this->actingAs($user)->get(route('household.registered.index'))
        ->assertInertia(fn ($page) => $page->component('household/Registered')->has('accounts', 1));
});

test('a room override requires an as-of date', function () {
    $user = User::factory()->create();
    $rrsp = registeredAccount($user);

    $this->actingAs($user)
        ->from(route('household.registered.index'))
        ->post(route('household.registered.store'), [
            'account_id' => $rrsp->id, 'type' => 'room_override', 'amount' => 100000,
            'occurred_on' => '2026-02-01', 'plan_year' => 2026,
        ])
        ->assertSessionHasErrors('as_of');
});

test('an event cannot be recorded against another household account', function () {
    $user = User::factory()->create();
    $other = Account::factory()->create(['type' => 'rrsp']);

    $this->actingAs($user)
        ->from(route('household.registered.index'))
        ->post(route('household.registered.store'), [
            'account_id' => $other->id, 'type' => 'contribution', 'amount' => 100,
            'occurred_on' => '2026-02-01', 'plan_year' => 2026,
        ])
        ->assertSessionHasErrors('account_id');
});
