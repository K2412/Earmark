<?php

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;

test('register screen is closed without an invite', function () {
    $response = $this->get(route('register'));

    $response->assertForbidden();
});

test('register POST is closed without an invite', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Anyone',
        'email' => 'anyone@example.com',
        'password' => 'password-secret',
        'password_confirmation' => 'password-secret',
    ]);

    $response->assertForbidden();
});

test('register screen opens for a valid invite code', function () {
    $owner = User::factory()->create();
    $household = $owner->household();

    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $household->id,
        'email' => 'invited@example.com',
        'role' => HouseholdRole::Member,
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invite' => $invitation->code]));

    $response->assertOk();
});

test('register screen rejects an expired invite', function () {
    $owner = User::factory()->create();
    $household = $owner->household();

    $invitation = HouseholdInvitation::factory()->expired()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invite' => $invitation->code]));

    $response->assertForbidden();
});

test('register screen rejects an already-accepted invite', function () {
    $owner = User::factory()->create();
    $household = $owner->household();

    $invitation = HouseholdInvitation::factory()->accepted()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invite' => $invitation->code]));

    $response->assertForbidden();
});

test('valid invite remembers itself in the session for the POST', function () {
    $owner = User::factory()->create();
    $household = $owner->household();

    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);

    $this->get(route('register', ['invite' => $invitation->code]));

    $response = $this->post(route('register.store'), [
        'name' => 'Invited User',
        'email' => 'newuser@example.com',
        'password' => 'password-secret',
        'password_confirmation' => 'password-secret',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
});
