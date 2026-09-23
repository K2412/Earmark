<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function ledgerTransaction(User $user, array $attributes = []): Transaction
{
    $household = $user->household();

    return Transaction::factory()->create(array_merge([
        'household_id' => $household->id,
        'account_id' => Account::factory()->create(['household_id' => $household->id])->id,
        'created_by_user_id' => $user->id,
    ], $attributes));
}

test('the register filters by payee search', function () {
    $user = User::factory()->create();
    ledgerTransaction($user, ['payee' => 'Loblaws Downtown']);
    ledgerTransaction($user, ['payee' => 'Shell Gas']);

    $this->actingAs($user)
        ->get(route('household.transactions.index', ['payee' => 'loblaws']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.payee', 'Loblaws Downtown')
        );
});

test('the register filters by account, cleared, and reviewed state', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    ledgerTransaction($user, ['account_id' => $account->id, 'cleared' => true, 'reviewed' => false, 'payee' => 'Wanted']);
    ledgerTransaction($user, ['account_id' => $account->id, 'cleared' => false, 'reviewed' => false, 'payee' => 'NotCleared']);
    ledgerTransaction($user, ['cleared' => true, 'reviewed' => false, 'payee' => 'OtherAccount']);

    $this->actingAs($user)
        ->get(route('household.transactions.index', ['account_id' => $account->id, 'cleared' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.payee', 'Wanted')
        );
});

test('the register filters by amount range and source', function () {
    $user = User::factory()->create();
    ledgerTransaction($user, ['amount' => -5000, 'source' => 'manual', 'payee' => 'Small']);
    ledgerTransaction($user, ['amount' => -20000, 'source' => 'manual', 'payee' => 'Big']);

    $this->actingAs($user)
        ->get(route('household.transactions.index', ['amount_max' => -10000]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.payee', 'Big')
        );
});

test('the register paginates', function () {
    $user = User::factory()->create();
    Transaction::factory()->count(55)->create([
        'household_id' => $user->household()->id,
        'account_id' => Account::factory()->create(['household_id' => $user->household()->id])->id,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('household.transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 50)
            ->where('transactions.total', 55)
        );
});

test('a transaction can be edited and the change is recorded', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    $transaction = ledgerTransaction($user, ['payee' => 'Original', 'amount' => -1000]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->patch(route('household.transactions.update', $transaction), [
            'date' => '2026-07-01',
            'account_id' => $transaction->account_id,
            'payee' => 'Corrected',
            'category_id' => null,
            'bucket_id' => $bucket->id,
            'amount' => -8000,
            'memo' => 'fixed',
            'cleared' => true,
            'reviewed' => true,
        ])
        ->assertRedirect(route('household.transactions.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'payee' => 'Corrected',
        'amount' => -8000,
        'cleared' => true,
        'reviewed' => true,
    ]);
    $this->assertDatabaseHas('transaction_activities', [
        'household_id' => $household->id,
        'transaction_id' => $transaction->id,
        'user_id' => $user->id,
        'action' => 'updated',
    ]);
});

test('editing a transfer is forbidden', function () {
    $user = User::factory()->create();
    $transaction = ledgerTransaction($user, ['transfer_pair_id' => Str::ulid()]);

    $this->actingAs($user)
        ->patch(route('household.transactions.update', $transaction), [
            'date' => '2026-07-01',
            'account_id' => $transaction->account_id,
            'payee' => 'x',
            'amount' => -1,
            'cleared' => false,
            'reviewed' => false,
        ])
        ->assertForbidden();
});

test('a transaction can be deleted and the deletion is recorded', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $transaction = ledgerTransaction($user, ['payee' => 'Gone', 'amount' => -4200]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->delete(route('household.transactions.destroy', $transaction))
        ->assertRedirect(route('household.transactions.index'));

    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    $this->assertDatabaseHas('transaction_activities', [
        'household_id' => $household->id,
        'action' => 'deleted',
        'transaction_id' => null,
    ]);
});

test('a reconciled transaction cannot be deleted', function () {
    $user = User::factory()->create();
    $transaction = ledgerTransaction($user, ['reconciled' => true]);

    $this->actingAs($user)
        ->delete(route('household.transactions.destroy', $transaction))
        ->assertStatus(422);

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
});

test('transactions can be bulk marked reviewed', function () {
    $user = User::factory()->create();
    $a = ledgerTransaction($user, ['reviewed' => false]);
    $b = ledgerTransaction($user, ['reviewed' => false]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.review'), ['ids' => [$a->id, $b->id], 'reviewed' => true])
        ->assertRedirect(route('household.transactions.index'))
        ->assertSessionHasNoErrors();

    expect($a->refresh()->reviewed)->toBeTrue()
        ->and($b->refresh()->reviewed)->toBeTrue();
    $this->assertDatabaseHas('transaction_activities', ['action' => 'reviewed']);
});

test('the register shows the household activity trail', function () {
    $user = User::factory()->create();
    $transaction = ledgerTransaction($user, ['reviewed' => false]);

    $this->actingAs($user)
        ->post(route('household.transactions.review'), ['ids' => [$transaction->id], 'reviewed' => true]);

    $this->actingAs($user)
        ->get(route('household.transactions.index'))
        ->assertInertia(fn (Assert $page) => $page->has('activities', 1));
});

test('a user cannot edit or delete another household transaction', function () {
    $user = User::factory()->create();
    $other = ledgerTransaction(User::factory()->create());

    $this->actingAs($user)->patch(route('household.transactions.update', $other), [
        'date' => '2026-07-01', 'account_id' => $other->account_id, 'payee' => 'x',
        'amount' => -1, 'cleared' => false, 'reviewed' => false,
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('household.transactions.destroy', $other))->assertNotFound();
});
