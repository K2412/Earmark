<?php

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;

it('sends guests to the login screen', function () {
    visit('/household/dashboard')
        ->assertPathIs('/login')
        ->assertSee('Log in');
});

it('rejects registration without an invite', function () {
    visit('/register')
        ->assertSee('Registration is by invitation only.');
});

it('registers through a valid invite and lands on overview', function () {
    $owner = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $owner->household()->id,
        'email' => 'invited@example.com',
        'role' => HouseholdRole::Member,
        'invited_by' => $owner->id,
    ]);

    visit('/register?invite='.$invitation->code)
        ->fill('name', 'Bob Invitee')
        ->fill('email', 'bob@example.com')
        ->fill('password', 'password-secret')
        ->fill('password_confirmation', 'password-secret')
        ->click('@register-user-button')
        ->assertPathIs('/household/dashboard')
        ->assertSee('Unassigned Funds');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);

    $bob = User::query()->where('email', 'bob@example.com')->sole();

    expect($bob->household()->id)->toBe($owner->household()->id)
        ->and($bob->householdRole($owner->household()))->toBe(HouseholdRole::Member);
});

it('signs the owner in from the login screen', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/household/dashboard')
        ->assertSee('Unassigned Funds');

    $this->assertAuthenticated();
});
