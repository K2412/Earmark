<?php

use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payee\PayeeHistoryService;

test('it maps a payee to its most recently used category and bucket', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $old = Category::factory()->create(['household_id' => $household->id]);
    $new = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    Transaction::factory()->create([
        'household_id' => $household->id,
        'payee' => 'LOBLAWS #5025',
        'category_id' => $old->id,
        'bucket_id' => $bucket->id,
        'date' => '2026-01-01',
        'created_by_user_id' => $user->id,
    ]);
    Transaction::factory()->create([
        'household_id' => $household->id,
        'payee' => 'LOBLAWS #5025',
        'category_id' => $new->id,
        'bucket_id' => $bucket->id,
        'date' => '2026-06-01',
        'created_by_user_id' => $user->id,
    ]);

    $map = app(PayeeHistoryService::class)->mapFor(['LOBLAWS #5025'], $household);

    // Newest categorization wins.
    expect($map['loblaws #5025'])->toBe(['category_id' => $new->id, 'bucket_id' => $bucket->id]);
});

test('it matches payees case-insensitively', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    Transaction::factory()->create([
        'household_id' => $household->id,
        'payee' => 'Netflix',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'created_by_user_id' => $user->id,
    ]);

    $map = app(PayeeHistoryService::class)->mapFor(['NETFLIX'], $household);

    expect($map['netflix']['category_id'])->toBe($category->id);
});

test('it ignores uncategorized transactions', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    Transaction::factory()->create([
        'household_id' => $household->id,
        'payee' => 'MYSTERY',
        'category_id' => null,
        'bucket_id' => $bucket->id,
        'created_by_user_id' => $user->id,
    ]);

    $map = app(PayeeHistoryService::class)->mapFor(['MYSTERY'], $household);

    expect($map)->not->toHaveKey('mystery');
});

test('it does not match another household', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $other = User::factory()->create();
    $otherHousehold = $other->household();
    $category = Category::factory()->create(['household_id' => $otherHousehold->id]);
    $bucket = Bucket::factory()->create(['household_id' => $otherHousehold->id]);

    Transaction::factory()->create([
        'household_id' => $otherHousehold->id,
        'payee' => 'SHARED NAME',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'created_by_user_id' => $other->id,
    ]);

    $map = app(PayeeHistoryService::class)->mapFor(['SHARED NAME'], $household);

    expect($map)->toBe([]);
});
