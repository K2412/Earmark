<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\DuplicateDetector;
use App\Support\Import\DraftRow;

beforeEach(function () {
    $this->detector = new DuplicateDetector;
});

test('flags a draft that matches an existing ledger transaction', function () {
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

    $results = $this->detector->inspect($account, [
        new DraftRow('2026-06-01', 'Loblaws', 'LOBLAWS', -5000),
    ]);

    expect($results[0]['reason'])->toContain('Matches an existing transaction')
        ->and($results[0]['duplicate_of_transaction_id'])->toBe($existing->id);
});

test('does not flag a draft with a different amount', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'date' => '2026-06-01',
        'amount' => -5000,
        'created_by_user_id' => $user->id,
    ]);

    $results = $this->detector->inspect($account, [
        new DraftRow('2026-06-01', 'Loblaws', 'LOBLAWS', -9999),
    ]);

    expect($results[0]['reason'])->toBeNull()
        ->and($results[0]['duplicate_of_transaction_id'])->toBeNull();
});

test('flags a row that repeats within the same batch', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $results = $this->detector->inspect($account, [
        new DraftRow('2026-06-01', 'Netflix', 'NETFLIX', -1699),
        new DraftRow('2026-06-01', 'Netflix', 'NETFLIX', -1699),
    ]);

    expect($results[0]['reason'])->toBeNull()
        ->and($results[1]['reason'])->toBe('Appears more than once in this file.');
});

test('produces a stable fingerprint independent of payee casing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);

    $a = $this->detector->fingerprint($account->id, new DraftRow('2026-06-01', 'Loblaws', 'x', -5000));
    $b = $this->detector->fingerprint($account->id, new DraftRow('2026-06-01', 'LOBLAWS', 'y', -5000));

    expect($a)->toBe($b)->toHaveLength(64);
});
