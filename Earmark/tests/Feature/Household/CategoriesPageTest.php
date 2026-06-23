<?php

use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

test('categories index renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('household.categories.index'))
        ->assertOk()
        ->assertSeeText('Categories');
});

test('categories index lists existing categories', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Groceries', 'type' => 'food']);
    Category::factory()->create(['name' => 'Rent', 'type' => 'housing']);

    $this->actingAs($user)
        ->get(route('household.categories.index'))
        ->assertSeeText('Groceries')
        ->assertSeeText('Rent');
});

test('create category via Livewire action persists', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::household.categories.index')
        ->set('form.name', 'Restaurants')
        ->set('form.type', 'food')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['name' => 'Restaurants', 'type' => 'food']);
});

test('create category fails on duplicate name', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Groceries']);

    Livewire::actingAs($user)
        ->test('pages::household.categories.index')
        ->set('form.name', 'Groceries')
        ->set('form.type', 'food')
        ->call('save')
        ->assertHasErrors(['form.name']);
});

test('guests are redirected from the categories page', function () {
    $this->get(route('household.categories.index'))
        ->assertRedirect(route('login'));
});
