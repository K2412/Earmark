<?php

use App\Models\Bucket;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('finance schema includes reporting categories buckets and effective transaction attribution', function () {
    expect(Schema::hasColumns('accounts', [
        'id',
        'name',
        'type',
        'starting_balance',
        'starting_balance_date',
        'archived',
        'sort_order',
    ]))->toBeTrue();

    expect(Schema::hasColumns('categories', [
        'id',
        'name',
        'type',
        'archived',
        'sort_order',
    ]))->toBeTrue();

    expect(Schema::hasColumns('buckets', [
        'id',
        'name',
        'kind',
        'monthly_obligation',
        'target_amount',
        'target_date',
        'archived',
        'archived_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('transactions', [
        'id',
        'date',
        'account_id',
        'payee',
        'category_id',
        'bucket_id',
        'amount',
        'is_split',
        'cleared',
        'reconciled',
        'created_by_user_id',
    ]))->toBeTrue();

    expect(Schema::hasColumns('transaction_splits', [
        'id',
        'transaction_id',
        'category_id',
        'bucket_id',
        'amount',
    ]))->toBeTrue();

    expect(Schema::hasColumns('payee_rules', [
        'id',
        'pattern',
        'category_id',
        'bucket_id',
        'priority',
        'auto_apply',
    ]))->toBeTrue();

    expect(Schema::hasColumns('reconciliations', [
        'id',
        'account_id',
        'statement_date',
        'statement_balance',
        'calculated_balance',
        'status',
        'reconciled_by_user_id',
    ]))->toBeTrue();

    expect(Schema::hasColumns('statement_uploads', [
        'id',
        'account_id',
        'original_filename',
        'file_sha256',
        'status',
        'uploaded_by_user_id',
    ]))->toBeTrue();

    expect(Schema::hasColumns('staged_transactions', [
        'id',
        'statement_upload_id',
        'date',
        'payee',
        'amount',
        'final_category_id',
        'final_bucket_id',
        'is_split',
    ]))->toBeTrue();

    expect(Schema::hasColumns('staged_transaction_splits', [
        'id',
        'staged_transaction_id',
        'category_id',
        'bucket_id',
        'amount',
    ]))->toBeTrue();

    expect(Schema::hasColumns('invites', [
        'id',
        'email',
        'token',
        'expires_at',
        'accepted_at',
        'invited_by_user_id',
    ]))->toBeTrue();
});

test('finance factories create valid attributed transactions and splits', function () {
    $transaction = Transaction::factory()->create();
    $split = TransactionSplit::factory()->create([
        'transaction_id' => $transaction->id,
        'amount' => $transaction->amount,
    ]);

    expect($transaction->account)->not->toBeNull()
        ->and($transaction->category)->not->toBeNull()
        ->and($transaction->bucket)->not->toBeNull()
        ->and($split->transaction->is($transaction))->toBeTrue()
        ->and($split->bucket)->not->toBeNull();
});

test('unassigned funds system bucket is seeded idempotently', function () {
    Artisan::call('db:seed', ['--class' => 'BucketSeeder']);
    Artisan::call('db:seed', ['--class' => 'BucketSeeder']);

    $bucket = Bucket::query()->where('name', 'Unassigned Funds')->sole();

    expect($bucket->kind)->toBe('system')
        ->and($bucket->target_date->toDateString())->toBe('9999-12-31')
        ->and(Bucket::query()->where('name', 'Unassigned Funds')->count())->toBe(1);
});
