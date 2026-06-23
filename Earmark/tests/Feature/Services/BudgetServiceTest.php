<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\BucketAssignment;
use App\Models\BucketObligationVersion;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\Budget\BudgetService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create();
    $this->service = new BudgetService;
});

test('obligationForMonth returns the latest effective version <= target month', function () {
    $bucket = Bucket::factory()->create(['monthly_obligation' => 10000]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 10000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 1,
        'created_by_user_id' => $this->user->id,
    ]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 15000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 6,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->obligationForMonth($bucket, 2026, 3))->toBe(10000)
        ->and($this->service->obligationForMonth($bucket, 2026, 6))->toBe(15000)
        ->and($this->service->obligationForMonth($bucket, 2026, 9))->toBe(15000);
});

test('edited obligation history applies only to current+future months (not past)', function () {
    $bucket = Bucket::factory()->create(['monthly_obligation' => 5000]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 5000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 1,
        'created_by_user_id' => $this->user->id,
    ]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 20000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 8,
        'created_by_user_id' => $this->user->id,
    ]);

    // March (before edit) still uses the original 5000
    expect($this->service->obligationForMonth($bucket, 2026, 3))->toBe(5000);
    // August (the edit) and after uses the new 20000
    expect($this->service->obligationForMonth($bucket, 2026, 8))->toBe(20000);
});

test('availableForMonth sums assignments in/out and transactions through month-end', function () {
    $source = Bucket::factory()->create();
    $target = Bucket::factory()->create();

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $target->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 30000,
        'created_by_user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'bucket_id' => $target->id,
        'account_id' => $this->account->id,
        'date' => '2026-06-15',
        'amount' => -8000,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->availableForMonth($target, 2026, 6))->toBe(22000);
});

test('availableForMonth carries prior-month balance forward (carryover)', function () {
    $bucket = Bucket::factory()->create();

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 50000,
        'created_by_user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'bucket_id' => $bucket->id,
        'account_id' => $this->account->id,
        'date' => '2026-03-20',
        'amount' => -15000,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->availableForMonth($bucket, 2026, 6))->toBe(35000)
        ->and($this->service->carryoverIntoMonth($bucket, 2026, 7))->toBe(35000);
});

test('availableForMonth excludes transactions after the target month-end', function () {
    $bucket = Bucket::factory()->create();

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 5,
        'amount' => 20000,
        'created_by_user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'bucket_id' => $bucket->id,
        'account_id' => $this->account->id,
        'date' => '2026-07-01',
        'amount' => -5000,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->availableForMonth($bucket, 2026, 6))->toBe(20000)
        ->and($this->service->availableForMonth($bucket, 2026, 7))->toBe(15000);
});

test('availableForMonth includes transaction splits', function () {
    $bucket = Bucket::factory()->create();
    $other = Bucket::factory()->create();

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 10000,
        'created_by_user_id' => $this->user->id,
    ]);

    $parent = Transaction::factory()->create([
        'bucket_id' => null,
        'category_id' => null,
        'is_split' => true,
        'account_id' => $this->account->id,
        'date' => '2026-06-15',
        'amount' => -9000,
        'created_by_user_id' => $this->user->id,
    ]);

    TransactionSplit::factory()->create([
        'transaction_id' => $parent->id,
        'bucket_id' => $bucket->id,
        'amount' => -3000,
    ]);
    TransactionSplit::factory()->create([
        'transaction_id' => $parent->id,
        'bucket_id' => $other->id,
        'amount' => -6000,
    ]);

    expect($this->service->availableForMonth($bucket, 2026, 6))->toBe(7000);
});

test('negative bucket math — bucket goes negative when spend exceeds assignments', function () {
    $bucket = Bucket::factory()->create();

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 5000,
        'created_by_user_id' => $this->user->id,
    ]);

    Transaction::factory()->create([
        'bucket_id' => $bucket->id,
        'account_id' => $this->account->id,
        'date' => '2026-06-15',
        'amount' => -15000,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->availableForMonth($bucket, 2026, 6))->toBe(-10000);
});

test('rolledForwardObligation accumulates unmet obligations from past months', function () {
    $bucket = Bucket::factory()->create(['monthly_obligation' => 20000]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 20000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 1,
        'created_by_user_id' => $this->user->id,
    ]);

    // Only assigned 8000 in Feb -> 12000 gap
    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 8000,
        'created_by_user_id' => $this->user->id,
    ]);

    // Met obligation in March -> 0 gap
    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 25000,
        'created_by_user_id' => $this->user->id,
    ]);

    // Jan gap=20000 (no assignment), Feb gap=12000, Mar gap=0 → rolled into April = 32000
    expect($this->service->rolledForwardObligation($bucket, 2026, 4))->toBe(32000);
});

test('isUnderfunded flags buckets that cannot cover obligation plus rolled-forward', function () {
    $bucket = Bucket::factory()->create(['monthly_obligation' => 10000]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 10000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 1,
        'created_by_user_id' => $this->user->id,
    ]);

    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $bucket->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 5000,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->isUnderfunded($bucket, 2026, 6))->toBeTrue();
});

test('underfundedBuckets returns only non-archived non-system buckets that are underfunded', function () {
    $under = Bucket::factory()->create(['monthly_obligation' => 50000, 'kind' => 'ongoing']);
    $funded = Bucket::factory()->create(['monthly_obligation' => 1000, 'kind' => 'ongoing']);
    $archived = Bucket::factory()->create(['monthly_obligation' => 50000, 'kind' => 'ongoing', 'archived' => true]);

    foreach ([$under, $funded, $archived] as $b) {
        BucketObligationVersion::create([
            'bucket_id' => $b->id,
            'monthly_obligation' => $b->monthly_obligation,
            'target_amount' => null,
            'target_date' => '2026-12-31',
            'effective_year' => 2026,
            'effective_month' => 1,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    // funded must cover Jan-Jun obligation (6 × 1000 = 6000) plus current-month rolled-forward
    BucketAssignment::create([
        'from_bucket_id' => null,
        'to_bucket_id' => $funded->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 10000,
        'created_by_user_id' => $this->user->id,
    ]);

    $result = $this->service->underfundedBuckets(2026, 6);

    expect($result->pluck('id'))->toContain($under->id)
        ->and($result->pluck('id'))->not->toContain($funded->id)
        ->and($result->pluck('id'))->not->toContain($archived->id);
});

test('archived bucket sweep — archived buckets are excluded from underfunded list even if obligation unmet', function () {
    $bucket = Bucket::factory()->create([
        'monthly_obligation' => 30000,
        'kind' => 'ongoing',
        'archived' => true,
    ]);

    BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 30000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 1,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($this->service->underfundedBuckets(2026, 6))->toHaveCount(0);
});
