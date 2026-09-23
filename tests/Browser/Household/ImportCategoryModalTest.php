<?php

use App\Models\Account;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;

it('creates a category from the import review modal', function () {
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
        'payee' => 'NETFLIX',
        'amount' => -1699,
        'status' => 'pending',
    ]);

    visit('/household/import/'.$upload->id.'/review')
        ->assertSee('Review import')
        ->click('@new-category')
        ->assertSee('Create a category')
        ->fill('@new-category-name', 'Streaming')
        ->select('@new-category-type', 'personal')
        ->click('@new-category-save')
        ->assertDontSee('Create a category') // modal closes once the round-trip succeeds
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('categories', [
        'household_id' => $household->id,
        'name' => 'Streaming',
        'type' => 'personal',
    ]);
});
