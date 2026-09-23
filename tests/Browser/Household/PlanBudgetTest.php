<?php

use App\Models\Bucket;

it('moves money between buckets from the plan page', function () {
    $user = actingAsOwner();
    $household = $user->household();
    $unassigned = Bucket::query()
        ->where('household_id', $household->id)
        ->where('name', Bucket::UNASSIGNED_FUNDS)
        ->sole();
    $food = Bucket::factory()->create([
        'household_id' => $household->id,
        'name' => 'Food',
        'kind' => 'ongoing',
    ]);

    visit('/household/plan')
        ->select('from_bucket_id', $unassigned->id)
        ->select('to_bucket_id', $food->id)
        ->fill('assign-amount', '5000')
        ->click('@submit-assign')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('bucket_assignments', [
        'from_bucket_id' => $unassigned->id,
        'to_bucket_id' => $food->id,
        'amount' => 5000,
    ]);
});
