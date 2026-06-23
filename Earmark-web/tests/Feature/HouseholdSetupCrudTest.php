<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

it('lets a household member create an account reporting category and bucket', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.accounts.store'), [
            'name' => 'Main Chequing',
            'type' => 'chequing',
            'starting_balance' => 125000,
            'starting_balance_date' => '2026-05-01',
        ])
        ->assertRedirect(route('household.accounts.index'));

    $this->actingAs($user)
        ->post(route('household.categories.store'), [
            'name' => 'Auto Repair',
            'type' => 'transportation',
        ])
        ->assertRedirect(route('household.categories.index'));

    $this->actingAs($user)
        ->post(route('household.buckets.store'), [
            'name' => 'Car Maintenance',
            'kind' => 'ongoing',
            'monthly_obligation' => 3000,
            'target_amount' => null,
            'target_date' => '9999-12-31',
        ])
        ->assertRedirect(route('household.buckets.index'));

    expect(Account::where('name', 'Main Chequing')->exists())->toBeTrue()
        ->and(Category::where('name', 'Auto Repair')->exists())->toBeTrue()
        ->and(Bucket::where('name', 'Car Maintenance')->exists())->toBeTrue();
});

it('archives regular buckets but blocks negative buckets', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create();

    $this->actingAs($user)
        ->delete(route('household.buckets.destroy', $bucket))
        ->assertRedirect(route('household.buckets.index'));

    expect($bucket->fresh()->archived)->toBeTrue();

    $negativeBucket = Bucket::factory()->create();
    Transaction::factory()->create([
        'bucket_id' => $negativeBucket->id,
        'amount' => -1000,
    ]);

    $this->actingAs($user)
        ->delete(route('household.buckets.destroy', $negativeBucket))
        ->assertForbidden();

    expect($negativeBucket->fresh()->archived)->toBeFalse();
});

it('does not allow the unassigned funds system bucket to be archived', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->system()->create([
        'name' => Bucket::UNASSIGNED_FUNDS,
    ]);

    $this->actingAs($user)
        ->delete(route('household.buckets.destroy', $bucket))
        ->assertForbidden();

    expect($bucket->fresh()->archived)->toBeFalse();
});
