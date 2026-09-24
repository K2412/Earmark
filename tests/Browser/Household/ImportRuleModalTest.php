<?php

use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;

it('creates a payee rule from an import review row', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);
    $category = Category::factory()->create(['household_id' => $household->id, 'name' => 'Streaming']);
    Bucket::factory()->create(['household_id' => $household->id, 'name' => 'Fun']);
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
        ->click('@make-rule')
        ->assertSee('Create a payee rule')
        ->fill('@rule-pattern', 'NETFLIX')
        ->select('#rule-category', $category->id)
        ->click('@rule-save')
        ->assertDontSee('Create a payee rule')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('payee_rules', [
        'household_id' => $household->id,
        'pattern' => 'NETFLIX',
        'category_id' => $category->id,
        'enabled' => true,
    ]);
});
