<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function integrityTransaction(User $user, array $attributes = []): Transaction
{
    $household = $user->household();

    return Transaction::factory()->create(array_merge([
        'household_id' => $household->id,
        'account_id' => Account::factory()->create(['household_id' => $household->id])->id,
        'category_id' => null,
        'bucket_id' => null,
        'created_by_user_id' => $user->id,
    ], $attributes));
}

test('a transaction can be split into allocations that sum to the parent', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $a = Bucket::factory()->create(['household_id' => $household->id]);
    $b = Bucket::factory()->create(['household_id' => $household->id]);
    $transaction = integrityTransaction($user, ['amount' => -10000]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.split', $transaction), [
            'splits' => [
                ['bucket_id' => $a->id, 'category_id' => null, 'amount' => -6000, 'memo' => null],
                ['bucket_id' => $b->id, 'category_id' => null, 'amount' => -4000, 'memo' => 'rest'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $transaction->refresh();
    expect($transaction->is_split)->toBeTrue()
        ->and($transaction->category_id)->toBeNull()
        ->and($transaction->splits()->count())->toBe(2)
        ->and($transaction->splits()->sum('amount'))->toBe(-10000);
});

test('splits that do not sum to the parent are rejected', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create(['household_id' => $user->household()->id]);
    $transaction = integrityTransaction($user, ['amount' => -10000]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.split', $transaction), [
            'splits' => [['bucket_id' => $bucket->id, 'category_id' => null, 'amount' => -6000, 'memo' => null]],
        ])
        ->assertSessionHasErrors('splits');

    expect($transaction->refresh()->is_split)->toBeFalse();
});

test('clearing the split removes the children', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create(['household_id' => $user->household()->id]);
    $transaction = integrityTransaction($user, ['amount' => -5000, 'is_split' => true]);
    $transaction->splits()->create(['bucket_id' => $bucket->id, 'amount' => -5000]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.split', $transaction), ['splits' => []])
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->is_split)->toBeFalse()
        ->and($transaction->splits()->count())->toBe(0);
});

test('a transfer cannot be split', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create(['household_id' => $user->household()->id]);
    $transfer = integrityTransaction($user, ['amount' => -5000, 'transfer_pair_id' => Str::ulid()]);

    $this->actingAs($user)
        ->post(route('household.transactions.split', $transfer), [
            'splits' => [['bucket_id' => $bucket->id, 'category_id' => null, 'amount' => -5000, 'memo' => null]],
        ])
        ->assertForbidden();
});

test('a transfer pair can be edited and stays balanced', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $from = Account::factory()->create(['household_id' => $household->id]);
    $to = Account::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)->post(route('household.transfers.store'), [
        'date' => '2026-06-01',
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 5000,
    ]);

    $out = Transaction::query()->whereNotNull('transfer_pair_id')->where('amount', '<', 0)->sole();

    $this->actingAs($user)
        ->patch(route('household.transfers.update', $out), [
            'date' => '2026-06-05',
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 8000,
        ])
        ->assertRedirect(route('household.transfers.index'));

    $out->refresh();
    $in = Transaction::query()->where('transfer_pair_id', $out->transfer_pair_id)->where('amount', '>', 0)->sole();

    expect($out->amount)->toBe(-8000)
        ->and($in->amount)->toBe(8000)
        ->and($out->date->toDateString())->toBe('2026-06-05');
});

test('a transfer edit to the same account is rejected', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $from = Account::factory()->create(['household_id' => $household->id]);
    $to = Account::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)->post(route('household.transfers.store'), [
        'date' => '2026-06-01', 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 5000,
    ]);
    $out = Transaction::query()->whereNotNull('transfer_pair_id')->where('amount', '<', 0)->sole();

    $this->actingAs($user)
        ->from(route('household.transfers.index'))
        ->patch(route('household.transfers.update', $out), [
            'date' => '2026-06-05', 'from_account_id' => $from->id, 'to_account_id' => $from->id, 'amount' => 8000,
        ])
        ->assertSessionHasErrors('to_account_id');
});
