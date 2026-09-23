<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\PayeeRule;
use App\Models\StatementUpload;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<int, array<string, string>>  $rows
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importPayload(string $accountId, array $rows, array $overrides = []): array
{
    return array_replace_recursive([
        'account_id' => $accountId,
        'source' => 'csv',
        'file' => [
            'name' => 'statement.csv',
            'size' => 2048,
            'sha256' => hash('sha256', 'statement-'.$accountId),
        ],
        'mapping' => [
            'date_format' => 'YYYY-MM-DD',
            'amount_mode' => 'single',
            'sign' => 'negative_is_outflow',
        ],
        'rows' => $rows,
    ], $overrides);
}

test('import page renders with the household accounts', function () {
    $user = User::factory()->create();
    Account::factory()->create(['household_id' => $user->household()->id, 'name' => 'Everyday Chequing']);

    $this->actingAs($user)
        ->get(route('household.import.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Import')
            ->has('accounts', 1)
            ->has('dateFormats')
        );
});

test('guests are redirected from the import page', function () {
    $this->get(route('household.import.index'))->assertRedirect(route('login'));
});

test('staging a csv creates an upload and staged rows but no ledger rows', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)
        ->post(route('household.import.store'), importPayload($account->id, [
            ['date' => '2026-06-01', 'payee' => 'Loblaws', 'amount' => '-72.50'],
            ['date' => '2026-06-02', 'payee' => 'Payroll', 'amount' => '2000.00'],
        ]))
        ->assertRedirect(route('household.import.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('statement_uploads', [
        'household_id' => $household->id,
        'account_id' => $account->id,
        'original_filename' => 'statement.csv',
        'status' => 'parsed',
        'parsed_transaction_count' => 2,
    ]);

    $this->assertDatabaseHas('staged_transactions', ['payee' => 'Loblaws', 'amount' => -7250]);
    $this->assertDatabaseHas('staged_transactions', ['payee' => 'Payroll', 'amount' => 200000]);

    // Extractors never write ledger rows.
    $this->assertDatabaseCount('transactions', 0);
});

test('re-importing the same file is rejected by fingerprint', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);
    $payload = importPayload($account->id, [['date' => '2026-06-01', 'payee' => 'Loblaws', 'amount' => '-10.00']]);

    $this->actingAs($user)->post(route('household.import.store'), $payload)->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.import.store'), $payload)
        ->assertSessionHasErrors('file.sha256');

    expect(StatementUpload::count())->toBe(1);
});

test('a staged row matching an existing transaction is flagged and not accepted', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $existing = Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'date' => '2026-06-01',
        'amount' => -5000,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('household.import.store'), importPayload($account->id, [
            ['date' => '2026-06-01', 'payee' => 'Loblaws', 'amount' => '-50.00'],
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'amount' => -5000,
        'is_possible_duplicate' => true,
        'accept' => false,
        'duplicate_of_transaction_id' => $existing->id,
    ]);
});

test('skipped rows are excluded from the staged count', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)
        ->post(route('household.import.store'), importPayload($account->id, [
            ['date' => 'garbage', 'payee' => 'Bad', 'amount' => '-10.00'],
            ['date' => '2026-06-01', 'payee' => 'Good', 'amount' => '-20.00'],
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('statement_uploads', ['parsed_transaction_count' => 1]);
    $this->assertDatabaseMissing('staged_transactions', ['payee' => 'Bad']);
});

test('an account from another household is rejected', function () {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.import.store'), importPayload($otherAccount->id, [
            ['date' => '2026-06-01', 'payee' => 'X', 'amount' => '-1.00'],
        ]))
        ->assertSessionHasErrors('account_id');
});

test('payee rules seed the staged suggestion', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    PayeeRule::factory()->create([
        'household_id' => $household->id,
        'pattern' => 'loblaws',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
    ]);

    $this->actingAs($user)
        ->post(route('household.import.store'), importPayload($account->id, [
            ['date' => '2026-06-01', 'payee' => 'LOBLAWS #5025', 'amount' => '-40.00'],
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'LOBLAWS #5025',
        'suggested_category_id' => $category->id,
        'suggested_bucket_id' => $bucket->id,
    ]);
});
