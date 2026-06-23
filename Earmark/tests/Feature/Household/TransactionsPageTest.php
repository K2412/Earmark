<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

test('transactions index renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.transactions.index'))
        ->assertOk()
        ->assertSeeText('Transactions');
});

test('transactions index lists transactions newest-first', function () {
    $user = User::factory()->create();
    Transaction::factory()->create(['payee' => 'Older', 'date' => '2026-01-01']);
    Transaction::factory()->create(['payee' => 'Newer', 'date' => '2026-06-01']);

    $this->actingAs($user)
        ->get(route('household.transactions.index'))
        ->assertSeeTextInOrder(['Newer', 'Older']);
});

test('create transaction via Livewire action persists with category and bucket attribution', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $category = Category::factory()->create();
    $bucket = Bucket::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.transactions.index')
        ->set('form.date', '2026-06-23')
        ->set('form.account_id', $account->id)
        ->set('form.payee', 'Loblaws')
        ->set('form.category_id', $category->id)
        ->set('form.bucket_id', $bucket->id)
        ->set('form.amount', -7250)
        ->set('form.memo', 'Weekly groceries')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'payee' => 'Loblaws',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'bucket_id' => $bucket->id,
        'amount' => -7250,
        'source' => 'manual',
        'created_by_user_id' => $user->id,
    ]);
});

test('create transaction fails for unknown account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.transactions.index')
        ->set('form.date', '2026-06-23')
        ->set('form.account_id', 'does-not-exist')
        ->set('form.payee', 'X')
        ->set('form.amount', -100)
        ->call('save')
        ->assertHasErrors(['form.account_id']);
});

test('guests are redirected from the transactions page', function () {
    $this->get(route('household.transactions.index'))
        ->assertRedirect(route('login'));
});
