<?php

use App\Actions\Transactions\TransferFunds;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

test('transfer creates two transactions sharing transfer_pair_id', function () {
    $user = User::factory()->create();
    $checking = Account::factory()->create(['name' => 'Chequing']);
    $savings = Account::factory()->create(['name' => 'Savings']);

    [$out, $in] = TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'amount' => 50000,
        'memo' => 'Monthly savings',
    ], $user);

    expect($out->amount)->toBe(-50000)
        ->and($in->amount)->toBe(50000)
        ->and($out->account_id)->toBe($checking->id)
        ->and($in->account_id)->toBe($savings->id)
        ->and($out->transfer_pair_id)->toBe($in->transfer_pair_id)
        ->and($out->transfer_pair_id)->not->toBeNull()
        ->and($out->created_by_user_id)->toBe($user->id);
});

test('transfer rejects same source and destination', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    expect(fn () => TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => 1000,
        'memo' => null,
    ], $user))->toThrow(InvalidArgumentException::class);
});

test('transfer rejects non-positive amount', function () {
    $user = User::factory()->create();
    $a = Account::factory()->create();
    $b = Account::factory()->create();

    expect(fn () => TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 0,
        'memo' => null,
    ], $user))->toThrow(InvalidArgumentException::class);
});

test('deleting one half of a transfer cascades to its sibling', function () {
    $user = User::factory()->create();
    $a = Account::factory()->create();
    $b = Account::factory()->create();

    [$out, $in] = TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 1000,
        'memo' => null,
    ], $user);

    $out->delete();

    expect(Transaction::find($out->id))->toBeNull()
        ->and(Transaction::find($in->id))->toBeNull();
});

test('transfers index lists transfers (from outflow rows only)', function () {
    $user = User::factory()->create();
    $a = Account::factory()->create(['name' => 'Chequing']);
    $b = Account::factory()->create(['name' => 'Savings']);
    TransferFunds::run([
        'date' => '2026-06-23',
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 25000,
        'memo' => null,
    ], $user);

    $this->actingAs($user)
        ->get(route('household.transfers.index'))
        ->assertOk()
        ->assertSeeText('Chequing')
        ->assertSeeText('Savings')
        ->assertSeeText('250.00');
});

test('Livewire save creates the pair and validates same-account guard', function () {
    $user = User::factory()->create();
    $a = Account::factory()->create();
    $b = Account::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.transfers.index')
        ->set('form.date', '2026-06-23')
        ->set('form.from_account_id', $a->id)
        ->set('form.to_account_id', $b->id)
        ->set('form.amount', 10000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::whereNotNull('transfer_pair_id')->count())->toBe(2);
});

test('Livewire save rejects same source+dest via Form rule', function () {
    $user = User::factory()->create();
    $a = Account::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.transfers.index')
        ->set('form.date', '2026-06-23')
        ->set('form.from_account_id', $a->id)
        ->set('form.to_account_id', $a->id)
        ->set('form.amount', 10000)
        ->call('save')
        ->assertHasErrors(['form.to_account_id']);
});

test('guests are redirected from the transfers page', function () {
    $this->get(route('household.transfers.index'))
        ->assertRedirect(route('login'));
});
