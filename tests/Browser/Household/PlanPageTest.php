<?php

it('creates an ongoing bucket and categories of selected types and moves the month', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();

    $page = visit('/household/plan');

    $page->assertSee('September 2026')
        ->assertSee('Unassigned Funds')
        ->fill('#bucket-name', 'Groceries')
        ->select('kind', 'ongoing')
        ->fill('monthly_obligation', '20000')
        ->click('@submit-create-bucket')
        ->assertSee('Groceries')
        ->assertSee('$200.00')
        ->assertSee('Underfunded')
        ->fill('#category-name', 'Food')
        ->select('type', 'food')
        ->click('@submit-create-category')
        ->assertSee('1 categories already on this household.')
        ->fill('#category-name', 'Salary')
        ->select('type', 'income')
        ->click('@submit-create-category')
        ->assertSee('2 categories already on this household.')
        ->fill('#category-name', 'Rent')
        ->select('type', 'housing')
        ->click('@submit-create-category')
        ->assertSee('3 categories already on this household.')
        ->click('@plan-next-month')
        ->assertSee('October 2026')
        ->click('@plan-prev-month')
        ->click('@plan-prev-month')
        ->assertSee('August 2026');

    $this->assertDatabaseHas('buckets', [
        'household_id' => $user->household()->id,
        'name' => 'Groceries',
        'kind' => 'ongoing',
        'monthly_obligation' => 20000,
    ]);
    $this->assertDatabaseHas('categories', [
        'household_id' => $user->household()->id,
        'name' => 'Food',
        'type' => 'food',
    ]);
    $this->assertDatabaseHas('categories', [
        'household_id' => $user->household()->id,
        'name' => 'Salary',
        'type' => 'income',
    ]);
    $this->assertDatabaseHas('categories', [
        'household_id' => $user->household()->id,
        'name' => 'Rent',
        'type' => 'housing',
    ]);
});

it('creates a goal bucket from the plan screen', function () {
    $this->travelTo('2026-09-07 12:00:00');

    $user = actingAsOwner();

    visit('/household/plan')
        ->fill('#bucket-name', 'Vacation')
        ->select('kind', 'goal')
        ->fill('monthly_obligation', '10000')
        ->click('@submit-create-bucket')
        ->assertSee('Vacation')
        ->assertSee('$100.00');

    $this->assertDatabaseHas('buckets', [
        'household_id' => $user->household()->id,
        'name' => 'Vacation',
        'kind' => 'goal',
        'monthly_obligation' => 10000,
    ]);
});
