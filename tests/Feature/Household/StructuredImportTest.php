<?php

use App\Models\Account;
use App\Models\StatementUpload;
use App\Models\Transaction;
use App\Models\User;

function ofxContent(): string
{
    return <<<'OFX'
        OFXHEADER:100
        DATA:OFXSGML
        VERSION:102

        <OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
        <STMTTRN><DTPOSTED>20260601<TRNAMT>-72.50<FITID>2026060101<NAME>LOBLAWS #5025</STMTTRN>
        <STMTTRN><DTPOSTED>20260602<TRNAMT>2000.00<FITID>2026060202<NAME>PAYROLL DEPOSIT</STMTTRN>
        </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
        OFX;
}

function qifContent(): string
{
    return <<<'QIF'
        !Type:Bank
        D06/01'2026
        T-72.50
        PLOBLAWS #5025
        ^
        QIF;
}

test('staging an ofx export creates staged rows with the bank id and no ledger rows', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)
        ->post(route('household.import.structured.store'), [
            'account_id' => $account->id,
            'source' => 'ofx',
            'filename' => 'statement.ofx',
            'content' => ofxContent(),
        ])
        ->assertRedirect(route('household.import.review', StatementUpload::sole()))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('statement_uploads', [
        'account_id' => $account->id,
        'source' => 'ofx',
        'parser_version' => 'earmark.ofx.v1',
        'parsed_transaction_count' => 2,
    ]);
    $this->assertDatabaseHas('staged_transactions', [
        'payee' => 'LOBLAWS #5025',
        'amount' => -7250,
        'external_id' => '2026060101',
    ]);
    $this->assertDatabaseCount('transactions', 0);
});

test('an ofx row matching an already-imported bank id is flagged, not accepted', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $existing = Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'date' => '2020-01-01', // deliberately different date/amount: only the bank id matches
        'amount' => -1,
        'external_id' => '2026060101',
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('household.import.structured.store'), [
            'account_id' => $account->id,
            'source' => 'ofx',
            'filename' => 'statement.ofx',
            'content' => ofxContent(),
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'external_id' => '2026060101',
        'is_possible_duplicate' => true,
        'accept' => false,
        'duplicate_of_transaction_id' => $existing->id,
    ]);
});

test('a qif row matching an existing transaction is flagged by content fingerprint', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $existing = Transaction::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'date' => '2026-06-01',
        'amount' => -7250,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('household.import.structured.store'), [
            'account_id' => $account->id,
            'source' => 'qif',
            'filename' => 'statement.qif',
            'content' => qifContent(),
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('staged_transactions', [
        'amount' => -7250,
        'external_id' => null,
        'is_possible_duplicate' => true,
        'accept' => false,
        'duplicate_of_transaction_id' => $existing->id,
    ]);
});

test('re-importing the same structured file is rejected by fingerprint', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);
    $payload = [
        'account_id' => $account->id,
        'source' => 'ofx',
        'filename' => 'statement.ofx',
        'content' => ofxContent(),
    ];

    $this->actingAs($user)->post(route('household.import.structured.store'), $payload)->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.import.structured.store'), $payload)
        ->assertSessionHasErrors('file_sha256');

    expect(StatementUpload::count())->toBe(1);
});

test('a malformed structured file is rejected without creating an upload', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.import.structured.store'), [
            'account_id' => $account->id,
            'source' => 'ofx',
            'filename' => 'broken.ofx',
            'content' => 'not really an ofx document',
        ])
        ->assertSessionHasErrors('content');

    $this->assertDatabaseCount('statement_uploads', 0);
    $this->assertDatabaseCount('staged_transactions', 0);
});

test('promoting an ofx import writes the bank id and source to the ledger', function () {
    $user = User::factory()->create();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    $this->actingAs($user)->post(route('household.import.structured.store'), [
        'account_id' => $account->id,
        'source' => 'ofx',
        'filename' => 'statement.ofx',
        'content' => ofxContent(),
    ])->assertSessionHasNoErrors();

    $upload = StatementUpload::sole();

    $this->actingAs($user)
        ->post(route('household.import.promote', $upload))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'account_id' => $account->id,
        'amount' => -7250,
        'source' => 'imported_ofx',
        'external_id' => '2026060101',
        'import_batch_id' => $upload->id,
    ]);
});

test('a structured import into another household account is rejected', function () {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.import.structured.store'), [
            'account_id' => $otherAccount->id,
            'source' => 'ofx',
            'filename' => 'statement.ofx',
            'content' => ofxContent(),
        ])
        ->assertSessionHasErrors('account_id');
});
