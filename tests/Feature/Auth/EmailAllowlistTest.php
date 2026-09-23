<?php

use App\Models\HouseholdInvitation;
use App\Models\User;

test('non-allowlisted email is bounced home when signing in', function () {
    $user = User::factory()->create(['email' => 'stranger@example.com']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

test('non-allowlisted email is bounced home when signing up, even with a valid invite', function () {
    $owner = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $owner->household()->id,
        'invited_by' => $owner->id,
    ]);

    $this->get(route('register', ['invite' => $invitation->code]));

    $response = $this->post(route('register.store'), [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'password-secret',
        'password_confirmation' => 'password-secret',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);
});

test('non-allowlisted email is bounced home when requesting a password reset', function () {
    $response = $this->post(route('password.email'), [
        'email' => 'stranger@example.com',
    ]);

    $response->assertRedirect(route('home'));
});

test('allowlisted email may sign up through an invite and the check ignores casing', function () {
    $owner = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $owner->household()->id,
        'invited_by' => $owner->id,
    ]);

    $this->get(route('register', ['invite' => $invitation->code]));

    $response = $this->post(route('register.store'), [
        'name' => 'Allowed Person',
        'email' => strtoupper(allowlistedEmail()),
        'password' => 'password-secret',
        'password_confirmation' => 'password-secret',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    expect(User::count())->toBe(2);
});

test('an empty allowlist denies everyone', function () {
    $user = User::factory()->create(['email' => allowlistedEmail()]);

    config()->set('access.auth_allowlist', []);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
