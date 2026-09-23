<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

function ownerTransaction(User $user, array $attributes = []): Transaction
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

test('a transaction can be assigned for review and resolved, with an audit trail', function () {
    $user = User::factory()->create();
    $transaction = ownerTransaction($user, ['reviewed' => true]);

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.assign', $transaction), ['assignee_id' => $user->id])
        ->assertSessionHasNoErrors();

    $transaction->refresh();
    expect($transaction->review_assignee_id)->toBe($user->id)
        ->and($transaction->reviewed)->toBeFalse();
    $this->assertDatabaseHas('transaction_activities', ['transaction_id' => $transaction->id, 'action' => 'assigned']);

    // Resolving (marking reviewed) clears the assignment.
    $this->actingAs($user)->post(route('household.transactions.review'), ['ids' => [$transaction->id], 'reviewed' => true]);

    $transaction->refresh();
    expect($transaction->reviewed)->toBeTrue()->and($transaction->review_assignee_id)->toBeNull();
});

test('the register can be filtered to my assignments', function () {
    $user = User::factory()->create();
    $mine = ownerTransaction($user, ['review_assignee_id' => $user->id, 'payee' => 'Mine']);
    ownerTransaction($user, ['payee' => 'Unassigned']);

    $this->actingAs($user)
        ->get(route('household.transactions.index', ['assigned' => 'me']))
        ->assertInertia(fn ($page) => $page->has('transactions.data', 1)->where('transactions.data.0.payee', 'Mine'));
});

test('an advisor has read-only access to the household', function () {
    $user = User::factory()->create();
    $user->householdMemberships()->update(['role' => 'advisor']);
    $account = Account::factory()->create(['household_id' => $user->household()->id]);

    // Reads are allowed.
    $this->actingAs($user)->get(route('household.transactions.index'))->assertOk();
    $this->actingAs($user)->get(route('household.net-worth.show'))->assertOk();

    // Writes are blocked.
    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.store'), [
            'date' => '2026-06-01', 'account_id' => $account->id, 'payee' => 'X', 'amount' => -100,
        ])
        ->assertForbidden();
});

test('a non-advisor member is not restricted by the advisor gate', function () {
    $user = User::factory()->create(); // owner
    $account = Account::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)
        ->post(route('household.transactions.store'), [
            'date' => '2026-06-01', 'account_id' => $account->id, 'payee' => 'X', 'amount' => -100,
        ])
        ->assertRedirect(route('household.transactions.index'));
});

test('an advisor can be invited with a least-privilege role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.members.invitations.store'), [
            'email' => 'advisor@example.test',
            'role' => 'advisor',
            'expires_at' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('household_invitations', ['email' => 'advisor@example.test', 'role' => 'advisor']);
});
