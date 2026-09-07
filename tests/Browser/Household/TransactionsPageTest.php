<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\PayeeRule;

it('creates an expense with a category, bucket, and memo', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Daily Chequing',
    ]);
    $category = Category::factory()->create([
        'household_id' => $household->id,
        'name' => 'Groceries',
        'type' => 'food',
    ]);
    $bucket = Bucket::factory()->create([
        'household_id' => $household->id,
        'name' => 'Food',
        'kind' => 'ongoing',
    ]);

    visit('/household/transactions')
        ->click('@open-create-transaction')
        ->select('account_id', $account->id)
        ->fill('payee', 'Loblaws')
        ->select('category_id', $category->id)
        ->select('bucket_id', $bucket->id)
        ->fill('amount', '-7250')
        ->fill('memo', 'Weekly shop')
        ->click('@submit-create-transaction')
        ->assertSee('Loblaws')
        ->assertSee('Groceries')
        ->assertSee('Food')
        ->assertSee('-$72.50');

    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'payee' => 'Loblaws',
        'amount' => -7250,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'memo' => 'Weekly shop',
    ]);
});

it('posts income without a bucket to Unassigned Funds', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Daily Chequing',
    ]);
    $unassigned = Bucket::query()
        ->where('household_id', $household->id)
        ->where('name', Bucket::UNASSIGNED_FUNDS)
        ->sole();

    visit('/household/transactions')
        ->click('@open-create-transaction')
        ->select('account_id', $account->id)
        ->fill('payee', 'Payroll')
        ->fill('amount', '200000')
        ->click('@submit-create-transaction')
        ->assertSee('Payroll')
        ->assertSee('$2,000.00')
        ->assertSee('Unassigned Funds');

    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'payee' => 'Payroll',
        'amount' => 200000,
        'bucket_id' => $unassigned->id,
    ]);
});

it('prefills category and bucket from a payee rule', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create([
        'household_id' => $household->id,
        'name' => 'Daily Chequing',
    ]);
    $category = Category::factory()->create([
        'household_id' => $household->id,
        'name' => 'Groceries',
        'type' => 'food',
    ]);
    $bucket = Bucket::factory()->create([
        'household_id' => $household->id,
        'name' => 'Food envelope',
        'kind' => 'ongoing',
    ]);
    PayeeRule::factory()->create([
        'household_id' => $household->id,
        'pattern' => 'loblaws',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
    ]);

    visit('/household/transactions')
        ->click('@open-create-transaction')
        ->select('account_id', $account->id)
        ->fill('payee', 'LOBLAWS #5025')
        ->fill('amount', '-7250')
        ->assertSee('Prefilled from payee rule')
        ->click('@submit-create-transaction')
        ->assertSee('LOBLAWS #5025')
        ->assertSee('Groceries')
        ->assertSee('Food envelope')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'payee' => 'LOBLAWS #5025',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'amount' => -7250,
    ]);
});

it('opens the transfers page from transactions', function () {
    actingAsOwner();

    visit('/household/transactions')
        ->click('Transfers')
        ->assertPathIs('/household/transfers')
        ->assertSee('No transfers yet');
});
