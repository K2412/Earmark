<?php

use App\Models\Category;
use App\Models\User;

test('a rule can be created inline and returns to the caller', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['household_id' => $user->household()->id]);

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.rules.inline'), [
            'name' => null,
            'pattern' => 'NETFLIX',
            'enabled' => true,
            'category_id' => $category->id,
            'bucket_id' => null,
            'rename_to' => null,
            'hide_from_reports' => false,
            'mark_for_review' => false,
            'auto_apply' => true,
        ])
        ->assertRedirect(route('household.import.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('payee_rules', [
        'household_id' => $user->household()->id,
        'pattern' => 'NETFLIX',
        'category_id' => $category->id,
    ]);
});

test('an inline rule requires a pattern', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.rules.inline'), [
            'pattern' => '',
            'enabled' => true,
            'hide_from_reports' => false,
            'mark_for_review' => false,
            'auto_apply' => true,
        ])
        ->assertSessionHasErrors('pattern');
});
