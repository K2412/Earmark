<?php

use App\Models\Bucket;

it('creates a save-up goal linked to a bucket', function () {
    $user = actingAsOwner();
    Bucket::factory()->create(['household_id' => $user->household()->id, 'name' => 'Vacation']);

    visit('/household/goals')
        ->click('@open-create-goal')
        ->fill('goal-name', 'Trip to Japan')
        ->fill('goal-target', '500000')
        ->click('@submit-goal')
        ->assertSee('Trip to Japan')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('goals', ['name' => 'Trip to Japan', 'type' => 'save_up']);
});
