<?php

use App\Models\Bucket;
use App\Models\BucketAssignment;
use App\Models\BucketObligationVersion;
use App\Models\User;
use Database\Seeders\BucketSeeder;
use Livewire\Livewire;

test('plan page renders with current month label', function () {
    $user = User::factory()->create();
    (new BucketSeeder)->run();

    $this->actingAs($user)
        ->get(route('household.plan.index'))
        ->assertOk()
        ->assertSeeText('Plan')
        ->assertSeeText(now()->format('F Y'));
});

test('plan rows expose obligation, available, needed, status per bucket', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create(['monthly_obligation' => 10000, 'name' => 'Groceries']);

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

    Livewire::actingAs($user)
        ->test('pages::household.plan.index')
        ->assertSeeText('Groceries')
        ->assertSeeText('150.00')
        ->assertSeeText('100.00')
        ->assertSeeText('OK');
});

test('previousMonth + nextMonth navigation updates the cursor', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::household.plan.index')
        ->assertSet('year', now()->year)
        ->assertSet('month', now()->month)
        ->call('previousMonth');

    $prior = now()->subMonth();
    $component->assertSet('year', $prior->year)->assertSet('month', $prior->month);

    $component->call('nextMonth')->call('nextMonth');
    $next = now()->addMonth();
    $component->assertSet('year', $next->year)->assertSet('month', $next->month);
});

test('guests are redirected from the plan page', function () {
    $this->get(route('household.plan.index'))
        ->assertRedirect(route('login'));
});
