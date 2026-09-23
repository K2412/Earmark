<?php

use App\Actions\Transactions\TransferFunds;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

test('transfer creates two transactions sharing transfer_pair_id', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $checking = Account::factory()->create(['household_id' => $household->id, 'name' => 'Chequing']);
    $savings = Account::factory()->create(['household_id' => $household->id, 'name' => 'Savings']);

    [$out, $in] = TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'amount' => 50000,
        'memo' => 'Monthly savings',
    ], $user, $household);

    expect($out->amount)->toBe(-50000)
        ->and($in->amount)->toBe(50000)
        ->and($out->account_id)->toBe($checking->id)
        ->and($in->account_id)->toBe($savings->id)
        ->and($out->transfer_pair_id)->toBe($in->transfer_pair_id)
        ->and($out->transfer_pair_id)->not->toBeNull()
        ->and($out->created_by_user_id)->toBe($user->id)
        ->and($out->household_id)->toBe($household->id);
});

test('transfer rejects same source and destination', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    expect(fn () => TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => 1000,
        'memo' => null,
    ], $user, $household))->toThrow(InvalidArgumentException::class);
});

test('transfer rejects non-positive amount', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $a = Account::factory()->create(['household_id' => $household->id]);
    $b = Account::factory()->create(['household_id' => $household->id]);

    expect(fn () => TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 0,
        'memo' => null,
    ], $user, $household))->toThrow(InvalidArgumentException::class);
});

test('deleting one half of a transfer cascades to its sibling', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $a = Account::factory()->create(['household_id' => $household->id]);
    $b = Account::factory()->create(['household_id' => $household->id]);

    [$out, $in] = TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 1000,
        'memo' => null,
    ], $user, $household);

    $out->delete();

    expect(Transaction::find($out->id))->toBeNull()
        ->and(Transaction::find($in->id))->toBeNull();
});

test('transfers index lists outflow rows only', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $a = Account::factory()->create(['household_id' => $household->id, 'name' => 'Chequing']);
    $b = Account::factory()->create(['household_id' => $household->id, 'name' => 'Savings']);

    TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 25000,
        'memo' => null,
    ], $user, $household);

    $this->actingAs($user)
        ->get(route('household.transfers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Transfers')
            ->has('transfers', 1)
            ->where('transfers.0.from', 'Chequing')
            ->where('transfers.0.to', 'Savings')
            ->where('transfers.0.amount', 25000)
            ->where('transfers.0.amount_formatted', Money::format(25000))
        );
});

test('store creates the pair and rejects same-account transfers', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $a = Account::factory()->create(['household_id' => $household->id]);
    $b = Account::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)
        ->post(route('household.transfers.store'), [
            'date' => '2026-06-23',
            'from_account_id' => $a->id,
            'to_account_id' => $b->id,
            'amount' => 10000,
        ])
        ->assertRedirect(route('household.transfers.index'));

    expect(Transaction::whereNotNull('transfer_pair_id')->count())->toBe(2);

    $this->actingAs($user)
        ->from(route('household.transfers.index'))
        ->post(route('household.transfers.store'), [
            'date' => '2026-06-23',
            'from_account_id' => $a->id,
            'to_account_id' => $a->id,
            'amount' => 10000,
        ])
        ->assertSessionHasErrors(['to_account_id']);
});

test('guests are redirected from the transfers page', function () {
    $this->get(route('household.transfers.index'))
        ->assertRedirect(route('login'));
});
