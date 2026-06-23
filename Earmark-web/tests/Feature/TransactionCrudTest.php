<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

it('creates a manual expense with reporting category and bucket attribution', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $category = Category::factory()->create(['type' => 'transportation']);
    $bucket = Bucket::factory()->create();

    $this->actingAs($user)
        ->post(route('household.transactions.store'), [
            'date' => '2026-05-24',
            'account_id' => $account->id,
            'payee' => 'Mechanic',
            'category_id' => $category->id,
            'bucket_id' => $bucket->id,
            'amount' => -45000,
            'memo' => 'Brake repair',
            'cleared' => true,
        ])
        ->assertRedirect(route('household.transactions.index'));

    $transaction = Transaction::query()->where('payee', 'Mechanic')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->category->is($category))->toBeTrue()
        ->and($transaction->bucket->is($bucket))->toBeTrue()
        ->and($transaction->amount)->toBe(-45000)
        ->and($transaction->createdBy->is($user))->toBeTrue();
});

it('defaults positive income to unassigned funds when no bucket is provided', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $category = Category::factory()->create(['type' => 'income']);
    $unassigned = Bucket::factory()->system()->create(['name' => Bucket::UNASSIGNED_FUNDS]);

    $this->actingAs($user)
        ->post(route('household.transactions.store'), [
            'date' => '2026-05-24',
            'account_id' => $account->id,
            'payee' => 'Employer',
            'category_id' => $category->id,
            'amount' => 300000,
        ])
        ->assertRedirect(route('household.transactions.index'));

    $transaction = Transaction::query()->where('payee', 'Employer')->first();

    expect($transaction->bucket->is($unassigned))->toBeTrue();
});
