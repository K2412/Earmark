<?php

use App\Models\HouseholdInvitation;
use App\Models\User;

test('registration screen is closed by default (invite-only)', function () {
    $response = $this->get(route('register'));

    $response->assertForbidden();
});

test('new users can register through a valid invite', function () {
    $owner = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $owner->household()->id,
        'invited_by' => $owner->id,
    ]);

    $this->get(route('register', ['invite' => $invitation->code]));

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
