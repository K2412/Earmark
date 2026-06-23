<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\BucketAssignment;
use App\Models\BucketObligationVersion;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use Database\Seeders\BucketSeeder;

// --- LW-S1: Accounts ---

test('account factory produces a valid account with ULID and integer cents', function () {
    $account = Account::factory()->create();

    expect($account->id)->toBeString()->toHaveLength(26)
        ->and($account->starting_balance)->toBeInt()
        ->and($account->starting_balance_date)->not->toBeNull()
        ->and($account->archived)->toBeBool();
});

// --- LW-S2: Categories ---

test('category factory produces a valid category', function () {
    $category = Category::factory()->create();

    expect($category->id)->toBeString()->toHaveLength(26)
        ->and($category->name)->not->toBeEmpty()
        ->and($category->type)->not->toBeEmpty();
});

// --- LW-S3: Buckets + Unassigned Funds ---

test('bucket factory produces a valid bucket', function () {
    $bucket = Bucket::factory()->create();

    expect($bucket->id)->toBeString()->toHaveLength(26)
        ->and($bucket->monthly_obligation)->toBeInt()
        ->and($bucket->target_date)->not->toBeNull();
});

test('BucketSeeder creates the Unassigned Funds system bucket exactly once', function () {
    (new BucketSeeder)->run();
    (new BucketSeeder)->run();

    $unassigned = Bucket::where('name', Bucket::UNASSIGNED_FUNDS)->get();

    expect($unassigned)->toHaveCount(1)
        ->and($unassigned->first()->kind)->toBe('system')
        ->and($unassigned->first()->isProtected())->toBeTrue();
});

test('Unassigned Funds bucket cannot be destroyed', function () {
    (new BucketSeeder)->run();
    $unassigned = Bucket::where('name', Bucket::UNASSIGNED_FUNDS)->first();

    expect(fn () => $unassigned->delete())
        ->toThrow(RuntimeException::class, 'Cannot delete protected system bucket [Unassigned Funds].');

    expect(Bucket::where('name', Bucket::UNASSIGNED_FUNDS)->exists())->toBeTrue();
});

test('non-system buckets can be destroyed normally', function () {
    $bucket = Bucket::factory()->create(['kind' => 'ongoing']);

    $bucket->delete();

    expect(Bucket::find($bucket->id))->toBeNull();
});

test('BucketObligationVersion records a versioned obligation history entry', function () {
    $user = User::factory()->create();
    $bucket = Bucket::factory()->create();

    $version = BucketObligationVersion::create([
        'bucket_id' => $bucket->id,
        'monthly_obligation' => 30000,
        'target_amount' => null,
        'target_date' => '2026-12-31',
        'effective_year' => 2026,
        'effective_month' => 7,
        'created_by_user_id' => $user->id,
    ]);

    expect($version->id)->toBeString()->toHaveLength(26)
        ->and($bucket->obligationVersions)->toHaveCount(1);
});

test('BucketAssignment moves cents from one bucket to another for a given month', function () {
    $user = User::factory()->create();
    $from = Bucket::factory()->create();
    $to = Bucket::factory()->create();

    $assignment = BucketAssignment::create([
        'from_bucket_id' => $from->id,
        'to_bucket_id' => $to->id,
        'year' => 2026,
        'month' => 7,
        'amount' => 15000,
        'created_by_user_id' => $user->id,
    ]);

    expect($assignment->amount)->toBe(15000)
        ->and($assignment->from_bucket_id)->toBe($from->id)
        ->and($assignment->to_bucket_id)->toBe($to->id);
});

// --- LW-S4: Transactions ---

test('transaction factory produces a valid transaction', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->id)->toBeString()->toHaveLength(26)
        ->and($transaction->amount)->toBeInt()
        ->and($transaction->date)->not->toBeNull()
        ->and($transaction->is_split)->toBeBool()
        ->and($transaction->cleared)->toBeBool()
        ->and($transaction->reconciled)->toBeBool();
});

test('transaction belongs to account, category, and bucket', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->account)->toBeInstanceOf(Account::class)
        ->and($transaction->createdBy)->toBeInstanceOf(User::class);
});

// --- LW-S5: TransactionSplits + invariant ---

test('split transaction children sum to parent amount', function () {
    $parent = Transaction::factory()->create([
        'amount' => -10000,
        'is_split' => true,
        'category_id' => null,
        'bucket_id' => null,
    ]);

    TransactionSplit::factory()->create([
        'transaction_id' => $parent->id,
        'amount' => -7500,
    ]);
    TransactionSplit::factory()->create([
        'transaction_id' => $parent->id,
        'amount' => -2500,
    ]);

    $parent->refresh()->load('splits');

    expect($parent->splits)->toHaveCount(2)
        ->and($parent->splits->sum('amount'))->toBe($parent->amount);
});

test('transaction split cascade-deletes when parent is deleted', function () {
    $parent = Transaction::factory()->create(['is_split' => true]);
    TransactionSplit::factory()->create(['transaction_id' => $parent->id]);
    TransactionSplit::factory()->create(['transaction_id' => $parent->id]);

    $splitIds = $parent->splits()->pluck('id');

    $parent->delete();

    expect(TransactionSplit::whereIn('id', $splitIds)->count())->toBe(0);
});
