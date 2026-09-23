<?php

use App\Models\Account;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;

it('filters review rows by status', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $upload = StatementUpload::factory()->create([
        'household_id' => $household->id,
        'account_id' => $account->id,
        'uploaded_by_user_id' => $user->id,
    ]);
    StagedTransaction::factory()->create([
        'statement_upload_id' => $upload->id,
        'payee' => 'ALPHA',
        'amount' => -1000,
        'status' => 'pending',
    ]);

    visit('/household/import/'.$upload->id.'/review')
        ->assertSee('Pending (1)')
        ->assertSee('Promoted (0)')
        ->click('@filter-promoted')
        ->assertSee('No promoted rows to show.')
        ->click('@filter-pending')
        ->assertDontSee('No promoted rows to show.')
        ->assertNoJavaScriptErrors();
});
