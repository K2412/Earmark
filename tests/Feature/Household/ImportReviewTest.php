<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Household;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Household, 2: StatementUpload}
 */
function reviewContext(): array
{
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $upload = StatementUpload::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'uploaded_by_user_id' => $user->id,
        'source' => 'csv',
    ]);

    return [$user, $household, $upload];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reviewRow(StagedTransaction $row, array $overrides = []): array
{
    return array_merge([
        'id' => $row->id,
        'date' => $row->date->toDateString(),
        'payee' => $row->payee,
        'amount' => $row->amount,
        'final_category_id' => $row->final_category_id,
        'final_bucket_id' => $row->final_bucket_id,
        'accept' => $row->accept,
        'status' => $row->status,
        'splits' => [],
    ], $overrides);
}

test('the review page renders staged rows', function () {
    [$user, $household, $upload] = reviewContext();
    StagedTransaction::factory()->create(['statement_upload_id' => $upload->id, 'payee' => 'Loblaws']);

    $this->actingAs($user)
        ->get(route('household.import.review', $upload))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/ImportReview')
            ->where('upload.filename', $upload->original_filename)
            ->has('rows', 1)
            ->has('categories')
            ->has('buckets')
        );
});

test('guests are redirected from the review page', function () {
    [, , $upload] = reviewContext();

    $this->get(route('household.import.review', $upload))->assertRedirect(route('login'));
});

test('corrections to a staged row are saved', function () {
    [$user, $household, $upload] = reviewContext();
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    $row = StagedTransaction::factory()->create(['statement_upload_id' => $upload->id, 'amount' => -1000]);

    $this->actingAs($user)
        ->patch(route('household.import.staged.update', $upload), [
            'rows' => [reviewRow($row, [
                'date' => '2026-07-01',
                'payee' => 'Corrected Payee',
                'amount' => -8000,
                'final_bucket_id' => $bucket->id,
                'accept' => true,
            ])],
        ])
        ->assertRedirect(route('household.import.review', $upload))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'id' => $row->id,
        'date' => '2026-07-01 00:00:00',
        'payee' => 'Corrected Payee',
        'amount' => -8000,
        'final_bucket_id' => $bucket->id,
    ]);
});

test('a staged row can be rejected', function () {
    [$user, $household, $upload] = reviewContext();
    $row = StagedTransaction::factory()->create(['statement_upload_id' => $upload->id]);

    $this->actingAs($user)
        ->patch(route('household.import.staged.update', $upload), [
            'rows' => [reviewRow($row, ['status' => 'rejected', 'accept' => false])],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', ['id' => $row->id, 'status' => 'rejected']);
});

test('splits that do not sum to the amount are rejected', function () {
    [$user, $household, $upload] = reviewContext();
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    $row = StagedTransaction::factory()->create(['statement_upload_id' => $upload->id, 'amount' => -10000]);

    $this->actingAs($user)
        ->patch(route('household.import.staged.update', $upload), [
            'rows' => [reviewRow($row, [
                'splits' => [['bucket_id' => $bucket->id, 'category_id' => null, 'amount' => -6000, 'memo' => null]],
            ])],
        ])
        ->assertSessionHasErrors('rows.0.splits');
});

test('splits that sum to the amount are persisted', function () {
    [$user, $household, $upload] = reviewContext();
    $groceries = Bucket::factory()->create(['household_id' => $household->id]);
    $home = Bucket::factory()->create(['household_id' => $household->id]);
    $row = StagedTransaction::factory()->create(['statement_upload_id' => $upload->id, 'amount' => -10000]);

    $this->actingAs($user)
        ->patch(route('household.import.staged.update', $upload), [
            'rows' => [reviewRow($row, [
                'splits' => [
                    ['bucket_id' => $groceries->id, 'category_id' => null, 'amount' => -6000, 'memo' => null],
                    ['bucket_id' => $home->id, 'category_id' => null, 'amount' => -4000, 'memo' => 'Home'],
                ],
            ])],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', ['id' => $row->id, 'is_split' => true]);
    expect($row->splits()->count())->toBe(2)
        ->and($row->splits()->sum('amount'))->toBe(-10000);
});

test('promoting from the endpoint writes to the ledger', function () {
    [$user, $household, $upload] = reviewContext();
    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'amount' => -4200,
        'accept' => true,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->post(route('household.import.promote', $upload))
        ->assertRedirect(route('household.import.review', $upload))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'import_batch_id' => $upload->id,
        'amount' => -4200,
        'source' => 'imported_csv',
    ]);
});

test('another household cannot review, correct, or promote an upload', function () {
    [$user] = reviewContext();
    $otherUpload = StatementUpload::factory()->create();
    $otherRow = StagedTransaction::factory()->create(['statement_upload_id' => $otherUpload->id]);

    $this->actingAs($user)->get(route('household.import.review', $otherUpload))->assertNotFound();
    $this->actingAs($user)->post(route('household.import.promote', $otherUpload))->assertNotFound();
    $this->actingAs($user)
        ->patch(route('household.import.staged.update', $otherUpload), [
            'rows' => [reviewRow($otherRow)],
        ])
        ->assertForbidden();
});
