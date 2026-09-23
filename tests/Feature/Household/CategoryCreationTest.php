<?php

use App\Models\Category;
use App\Models\User;

test('a household member can create a category and return to the caller', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.categories.store'), ['name' => 'Streaming', 'type' => 'personal'])
        ->assertRedirect(route('household.import.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'household_id' => $user->household()->id,
        'name' => 'Streaming',
        'type' => 'personal',
    ]);
});

test('creating a category requires a valid type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.categories.store'), ['name' => 'Whatever', 'type' => 'nonsense'])
        ->assertSessionHasErrors('type');

    $this->assertDatabaseMissing('categories', ['name' => 'Whatever']);
});

test('category names are unique within a household', function () {
    $user = User::factory()->create();
    Category::factory()->create(['household_id' => $user->household()->id, 'name' => 'Groceries']);

    $this->actingAs($user)
        ->from(route('household.import.index'))
        ->post(route('household.categories.store'), ['name' => 'Groceries', 'type' => 'food'])
        ->assertSessionHasErrors('name');
});

test('guests cannot create categories', function () {
    $this->post(route('household.categories.store'), ['name' => 'X', 'type' => 'other'])
        ->assertRedirect(route('login'));
});
