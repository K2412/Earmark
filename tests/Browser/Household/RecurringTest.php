<?php

use App\Models\Account;
use App\Models\Transaction;

it('confirms a detected recurring candidate', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    foreach (['2026-01-15', '2026-02-15', '2026-03-15'] as $date) {
        Transaction::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
            'payee' => 'Netflix',
            'amount' => -1699,
            'date' => $date,
            'reviewed' => true,
            'category_id' => null,
            'bucket_id' => null,
            'created_by_user_id' => $user->id,
        ]);
    }

    visit('/household/recurring')
        ->assertSee('Netflix')
        ->click('@confirm-candidate')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('recurring_schedules', ['name' => 'Netflix', 'status' => 'confirmed']);
});
