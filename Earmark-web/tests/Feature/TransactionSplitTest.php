<?php

use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\Transaction\EffectiveTransactionLineService;

it('splits a transaction into child lines with category and bucket attribution', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'amount' => -4000,
        'is_split' => false,
    ]);
    $household = Category::factory()->create(['type' => 'household']);
    $personal = Category::factory()->create(['type' => 'personal']);
    $householdBucket = Bucket::factory()->create(['name' => 'Household Items']);
    $funBucket = Bucket::factory()->create(['name' => 'Fun Money']);

    $this->actingAs($user)
        ->post(route('household.transactions.split.store', $transaction), [
            'splits' => [
                [
                    'category_id' => $household->id,
                    'bucket_id' => $householdBucket->id,
                    'amount' => -2000,
                ],
                [
                    'category_id' => $personal->id,
                    'bucket_id' => $funBucket->id,
                    'amount' => -2000,
                ],
            ],
        ])
        ->assertRedirect(route('household.transactions.index'));

    $lines = app(EffectiveTransactionLineService::class)->linesFor($transaction->fresh());

    expect($transaction->fresh()->is_split)->toBeTrue()
        ->and(TransactionSplit::where('transaction_id', $transaction->id)->count())->toBe(2)
        ->and($lines)->toHaveCount(2)
        ->and($lines->sum('amount'))->toBe(-4000);
});

it('rejects split rows that do not sum to the parent transaction amount', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create(['amount' => -4000]);
    $category = Category::factory()->create();
    $bucket = Bucket::factory()->create();

    $this->actingAs($user)
        ->post(route('household.transactions.split.store', $transaction), [
            'splits' => [
                [
                    'category_id' => $category->id,
                    'bucket_id' => $bucket->id,
                    'amount' => -1000,
                ],
            ],
        ])
        ->assertSessionHasErrors('splits');

    expect($transaction->fresh()->is_split)->toBeFalse();
});
