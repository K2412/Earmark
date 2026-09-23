<?php

use App\Models\Account;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;

it('promotes staged rows into the ledger from the review page', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $upload = StatementUpload::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'uploaded_by_user_id' => $user->id,
        'source' => 'csv',
    ]);
    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'payee' => 'LOBLAWS #5025',
        'amount' => -7250,
        'accept' => true,
        'status' => 'pending',
    ]);

    visit('/household/import/'.$upload->id.'/review')
        ->assertSee('Review import')
        ->click('@review-promote')
        ->assertSee('Promoted')
        ->assertSee('LOBLAWS #5025')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('transactions', [
        'import_batch_id' => $upload->id,
        'amount' => -7250,
        'source' => 'imported_csv',
    ]);
});
