<?php

use App\Models\Bucket;
use App\Models\BucketAssignment;
use App\Models\BucketObligationVersion;
use App\Models\User;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

test('plan page renders with current month label', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.plan.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Plan')
            ->where('monthLabel', now()->format('F Y'))
            ->has('rows')
        );
});

test('plan rows expose obligation, available, needed, and cents for status', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $bucket = Bucket::factory()->create([
        'household_id' => $household->id,
        'monthly_obligation' => 10000,
        'name' => 'Groceries',
    ]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 10000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => now()->year,
        'effective_month' => now()->month,
        'created_by_user_id' => $user->id,
    ]);

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => now()->year,
        'month' => now()->month,
        'amount' => 15000,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('household.plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Plan')
            ->has('rows.1', fn (Assert $row) => $row
                ->where('name', 'Groceries')
                ->where('available', Money::format(15000))
                ->where('obligation', Money::format(10000))
                ->where('available_cents', 15000)
                ->where('needed_cents', 10000)
                ->etc()
            )
        );
});

test('month query updates the cursor', function () {
    $user = User::factory()->create();
    $prior = now()->subMonth();

    $this->actingAs($user)
        ->get(route('household.plan.index', ['year' => $prior->year, 'month' => $prior->month]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('year', $prior->year)
            ->where('month', $prior->month)
            ->where('monthLabel', $prior->format('F Y'))
        );
});

test('create bucket and category from plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.plan.buckets.store'), [
            'name' => 'Groceries',
            'kind' => 'ongoing',
            'monthly_obligation' => 20000,
            'target_date' => '2027-01-01',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('household.plan.categories.store'), [
            'name' => 'Food',
            'type' => 'food',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('buckets', [
        'household_id' => $user->household()->id,
        'name' => 'Groceries',
        'monthly_obligation' => 20000,
    ]);
    $this->assertDatabaseHas('categories', [
        'household_id' => $user->household()->id,
        'name' => 'Food',
        'type' => 'food',
    ]);
});

test('guests are redirected from the plan page', function () {
    $this->get(route('household.plan.index'))
        ->assertRedirect(route('login'));
});
