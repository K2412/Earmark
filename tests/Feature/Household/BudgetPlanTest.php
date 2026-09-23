<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\BudgetService;

function unassignedBucket(User $user): Bucket
{
    return Bucket::query()
        ->where('household_id', $user->household()->id)
        ->where('name', Bucket::UNASSIGNED_FUNDS)
        ->sole();
}

test('assigning funds moves money between buckets and conserves every cent', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $unassigned = unassignedBucket($user);
    $food = Bucket::factory()->create(['household_id' => $household->id, 'kind' => 'ongoing', 'monthly_obligation' => 0]);
    $account = Account::factory()->create(['household_id' => $household->id]);

    // Income lands in Unassigned Funds.
    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'bucket_id' => $unassigned->id,
        'amount' => 10000,
        'date' => '2026-06-01',
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('household.plan.assign'), [
            'from_bucket_id' => $unassigned->id,
            'to_bucket_id' => $food->id,
            'amount' => 4000,
            'year' => 2026,
            'month' => 6,
        ])
        ->assertSessionHasNoErrors();

    $budget = app(BudgetService::class);

    expect($budget->availableForMonth($food, 2026, 6))->toBe(4000)
        ->and($budget->availableForMonth($unassigned, 2026, 6))->toBe(6000)
        // Conservation: total available across both buckets equals the income.
        ->and($budget->availableForMonth($food, 2026, 6) + $budget->availableForMonth($unassigned, 2026, 6))->toBe(10000);
});

test('assignments carry forward into future months', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $unassigned = unassignedBucket($user);
    $food = Bucket::factory()->create(['household_id' => $household->id, 'kind' => 'ongoing', 'monthly_obligation' => 0]);

    $this->actingAs($user)->post(route('household.plan.assign'), [
        'from_bucket_id' => $unassigned->id,
        'to_bucket_id' => $food->id,
        'amount' => 5000,
        'year' => 2026,
        'month' => 6,
    ]);

    $budget = app(BudgetService::class);

    expect($budget->availableForMonth($food, 2026, 7))->toBe(5000);
});

test('an effective-dated obligation change does not rewrite prior months', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $food = Bucket::factory()->create(['household_id' => $household->id, 'kind' => 'ongoing', 'monthly_obligation' => 0]);

    $this->actingAs($user)
        ->post(route('household.plan.buckets.obligation', $food), [
            'monthly_obligation' => 20000,
            'effective_year' => 2026,
            'effective_month' => 6,
        ])
        ->assertSessionHasNoErrors();

    $budget = app(BudgetService::class);

    expect($budget->obligationForMonth($food, 2026, 5))->toBe(0)      // prior month unchanged
        ->and($budget->obligationForMonth($food, 2026, 6))->toBe(20000) // effective onwards
        ->and($budget->obligationForMonth($food, 2026, 7))->toBe(20000);
});

test('assigning to the same bucket is rejected', function () {
    $user = User::factory()->create();
    $food = Bucket::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)
        ->from(route('household.plan.index'))
        ->post(route('household.plan.assign'), [
            'from_bucket_id' => $food->id,
            'to_bucket_id' => $food->id,
            'amount' => 1000,
            'year' => 2026,
            'month' => 6,
        ])
        ->assertSessionHasErrors('to_bucket_id');
});

test('a user cannot assign to or set obligations on another household bucket', function () {
    $user = User::factory()->create();
    $mine = Bucket::factory()->create(['household_id' => $user->household()->id]);
    $other = Bucket::factory()->create();

    $this->actingAs($user)
        ->from(route('household.plan.index'))
        ->post(route('household.plan.assign'), [
            'from_bucket_id' => $mine->id,
            'to_bucket_id' => $other->id,
            'amount' => 1000,
            'year' => 2026,
            'month' => 6,
        ])
        ->assertSessionHasErrors('to_bucket_id');

    $this->actingAs($user)
        ->post(route('household.plan.buckets.obligation', $other), [
            'monthly_obligation' => 100,
            'effective_year' => 2026,
            'effective_month' => 6,
        ])
        ->assertForbidden();
});
