<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\PayeeRule;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('transactions index renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('household/Transactions')->has('transactions'));
});

test('transactions index lists household transactions newest-first', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'payee' => 'Older',
        'date' => '2026-01-01',
        'created_by_user_id' => $user->id,
    ]);
    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'payee' => 'Newer',
        'date' => '2026-06-01',
        'created_by_user_id' => $user->id,
    ]);
    Transaction::factory()->create(['payee' => 'Other House']);

    $this->actingAs($user)
        ->get(route('household.transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Transactions')
            ->has('transactions.data', 2)
            ->where('transactions.data.0.payee', 'Newer')
            ->where('transactions.data.1.payee', 'Older')
        );
});

test('create transaction persists with category and bucket attribution', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)
        ->post(route('household.transactions.store'), [
            'date' => '2026-06-23',
            'account_id' => $account->id,
            'payee' => 'Loblaws',
            'category_id' => $category->id,
            'bucket_id' => $bucket->id,
            'amount' => -7250,
            'memo' => 'Weekly groceries',
        ])
        ->assertRedirect(route('household.transactions.index'));

    $this->assertDatabaseHas('transactions', [
        'household_id' => $household->id,
        'payee' => 'Loblaws',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'amount' => -7250,
        'source' => 'manual',
        'created_by_user_id' => $user->id,
    ]);
});

test('income without a bucket lands in Unassigned Funds', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $unassigned = Bucket::query()
        ->where('household_id', $household->id)
        ->where('name', Bucket::UNASSIGNED_FUNDS)
        ->sole();

    $this->actingAs($user)
        ->post(route('household.transactions.store'), [
            'date' => '2026-06-23',
            'account_id' => $account->id,
            'payee' => 'Salary',
            'amount' => 200000,
        ])
        ->assertRedirect(route('household.transactions.index'));

    $this->assertDatabaseHas('transactions', [
        'payee' => 'Salary',
        'amount' => 200000,
        'bucket_id' => $unassigned->id,
        'household_id' => $household->id,
    ]);
});

test('create transaction fails for unknown account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.transactions.index'))
        ->post(route('household.transactions.store'), [
            'date' => '2026-06-23',
            'account_id' => 'does-not-exist',
            'payee' => 'X',
            'amount' => -100,
        ])
        ->assertSessionHasErrors(['account_id']);
});

test('payee suggestion returns matching household rule', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $category = Category::factory()->create(['household_id' => $household->id]);
    $bucket = Bucket::factory()->create(['household_id' => $household->id]);
    $rule = PayeeRule::factory()->create([
        'household_id' => $household->id,
        'pattern' => 'loblaws',
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
    ]);

    $this->actingAs($user)
        ->get(route('household.transactions.suggest', ['payee' => 'LOBLAWS #5025']))
        ->assertOk()
        ->assertJson([
            'rule_id' => $rule->id,
            'category_id' => $category->id,
            'bucket_id' => $bucket->id,
        ]);
});

test('guests are redirected from the transactions page', function () {
    $this->get(route('household.transactions.index'))
        ->assertRedirect(route('login'));
});
