<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\PayeeRule;
use App\Models\Transaction;
use App\Models\User;

/**
 * @param  array<int, array<string, string>>  $rows
 */
function historyImport(User $user, string $accountId, array $rows): void
{
    test()->actingAs($user)->post(route('household.import.store'), [
        'account_id' => $accountId,
        'source' => 'csv',
        'file' => ['name' => 's.csv', 'size' => 64, 'sha256' => hash('sha256', 'hist-'.$accountId.'-'.json_encode($rows))],
        'mapping' => ['date_format' => 'YYYY-MM-DD', 'amount_mode' => 'single', 'sign' => 'negative_is_outflow'],
        'rows' => $rows,
    ])->assertSessionHasNoErrors();
}

test('import pre-fills the category and bucket from a previously categorized payee', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    // A transaction the user categorized by hand once — no payee rule exists.
    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'payee' => 'SPOTIFY',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'date' => '2026-05-01',
        'amount' => -1099,
        'created_by_user_id' => $user->id,
    ]);

    historyImport($user, $account->id, [
        ['date' => '2026-06-01', 'payee' => 'SPOTIFY', 'amount' => '-10.99'],
    ]);

    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'SPOTIFY',
        'suggested_category_id' => $category->id,
        'suggested_bucket_id' => $bucket->id,
        'final_category_id' => $category->id,
        'final_bucket_id' => $bucket->id,
    ]);
});

test('a payee rule takes precedence over history', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $historyCategory = Category::factory()->create(['household_id' => $household->id]);
    $historyBucket = Bucket::factory()->create(['household_id' => $household->id]);
    $ruleCategory = Category::factory()->create(['household_id' => $household->id]);
    $ruleBucket = Bucket::factory()->create(['household_id' => $household->id]);

    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'payee' => 'SPOTIFY',
        'category_id' => $historyCategory->id,
        'bucket_id' => $historyBucket->id,
        'date' => '2026-05-01',
        'created_by_user_id' => $user->id,
    ]);

    PayeeRule::factory()->create([
        'household_id' => $household->id,
        'pattern' => 'spotify',
        'category_id' => $ruleCategory->id,
        'bucket_id' => $ruleBucket->id,
    ]);

    historyImport($user, $account->id, [
        ['date' => '2026-06-01', 'payee' => 'SPOTIFY', 'amount' => '-10.99'],
    ]);

    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'SPOTIFY',
        'final_category_id' => $ruleCategory->id,
        'final_bucket_id' => $ruleBucket->id,
    ]);
});

test('a payee with no rule and no history is left uncategorized', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    historyImport($user, $account->id, [
        ['date' => '2026-06-01', 'payee' => 'BRAND NEW MERCHANT', 'amount' => '-5.00'],
    ]);

    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'BRAND NEW MERCHANT',
        'final_category_id' => null,
        'final_bucket_id' => null,
    ]);
});
