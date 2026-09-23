<?php

use App\Actions\Import\PromoteStatementImport;
use App\Models\Account;
use App\Models\Bucket;
use App\Models\Household;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;
use App\Models\Transaction;
use App\Models\User;

/**
 * @return array{0: User, 1: Household, 2: Account, 3: StatementUpload}
 */
function importContext(): array
{
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $upload = StatementUpload::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'uploaded_by_user_id' => $user->id,
        'source' => 'csv',
        'status' => 'parsed',
    ]);

    return [$user, $household, $account, $upload];
}

test('promotion writes accepted pending rows to the ledger with provenance', function () {
    [$user, $household, $account, $upload] = importContext();
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    $row = StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'date' => '2026-06-01',
        'payee' => 'Loblaws',
        'amount' => -7250,
        'accept' => true,
        'status' => 'pending',
        'final_bucket_id' => $bucket->id,
    ]);

    $counts = PromoteStatementImport::run($upload, $user, $household);

    expect($counts['promoted'])->toBe(1);

    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'account_id' => $account->id,
        'payee' => 'Loblaws',
        'amount' => -7250,
        'bucket_id' => $bucket->id,
        'source' => 'imported_csv',
        'import_batch_id' => $upload->id,
        'created_by_user_id' => $user->id,
    ]);

    $row->refresh();
    expect($row->status)->toBe('promoted')
        ->and($row->transaction_id)->not->toBeNull();

    $upload->refresh();
    expect($upload->status)->toBe('imported')
        ->and($upload->imported_transaction_count)->toBe(1);
});

test('promotion is idempotent on retry', function () {
    [$user, $household, $account, $upload] = importContext();

    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'amount' => -5000,
        'accept' => true,
        'status' => 'pending',
    ]);

    PromoteStatementImport::run($upload, $user, $household);
    $second = PromoteStatementImport::run($upload, $user, $household);

    expect($second['promoted'])->toBe(0)
        ->and(Transaction::count())->toBe(1);
});

test('rejected and unaccepted rows are not promoted', function () {
    [$user, $household, $account, $upload] = importContext();

    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'accept' => false,
        'status' => 'pending',
    ]);
    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'accept' => true,
        'status' => 'rejected',
    ]);

    $counts = PromoteStatementImport::run($upload, $user, $household);

    expect($counts['promoted'])->toBe(0)
        ->and($counts['rejected'])->toBe(1)
        ->and(Transaction::count())->toBe(0);
});

test('a split row promotes into a transaction with matching splits', function () {
    [$user, $household, $account, $upload] = importContext();
    $groceries = Bucket::factory()->create(['household_id' => $household->id]);
    $household2 = Bucket::factory()->create(['household_id' => $household->id]);

    $row = StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'amount' => -10000,
        'accept' => true,
        'status' => 'pending',
        'is_split' => true,
    ]);
    $row->splits()->createMany([
        ['bucket_id' => $groceries->id, 'amount' => -6000],
        ['bucket_id' => $household2->id, 'amount' => -4000],
    ]);

    $counts = PromoteStatementImport::run($upload, $user, $household);

    expect($counts['promoted'])->toBe(1);

    $transaction = Transaction::query()->where('import_batch_id', $upload->id)->sole();

    expect($transaction->is_split)->toBeTrue()
        ->and($transaction->amount)->toBe(-10000)
        ->and($transaction->splits()->count())->toBe(2)
        ->and($transaction->splits()->sum('amount'))->toBe(-10000);
});

test('a split row whose parts do not sum is counted as failed, not promoted', function () {
    [$user, $household, $account, $upload] = importContext();
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    $row = StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'amount' => -10000,
        'accept' => true,
        'status' => 'pending',
        'is_split' => true,
    ]);
    $row->splits()->create(['bucket_id' => $bucket->id, 'amount' => -6000]);

    $counts = PromoteStatementImport::run($upload, $user, $household);

    expect($counts['promoted'])->toBe(0)
        ->and($counts['failed'])->toBe(1)
        ->and(Transaction::count())->toBe(0);
});

test('income without a bucket promotes into Unassigned Funds', function () {
    [$user, $household, $account, $upload] = importContext();
    $unassigned = Bucket::query()
        ->where('household_id', $household->id)
        ->where('name', Bucket::UNASSIGNED_FUNDS)
        ->sole();

    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'amount' => 200000,
        'accept' => true,
        'status' => 'pending',
        'final_bucket_id' => null,
        'final_category_id' => null,
    ]);

    PromoteStatementImport::run($upload, $user, $household);

    $this->assertDatabaseHas('transactions', [
        'import_batch_id' => $upload->id,
        'amount' => 200000,
        'bucket_id' => $unassigned->id,
    ]);
});
