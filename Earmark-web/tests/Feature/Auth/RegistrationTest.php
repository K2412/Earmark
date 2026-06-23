<?php

use App\Models\User;

test('public registration screen is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration submission is disabled', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('first household user can be created from the command line', function () {
    $this->artisan('app:create-first-user', [
        '--name' => 'Operator',
        '--email' => 'operator@example.com',
        '--password' => 'password',
    ])->assertSuccessful();

    $user = User::where('email', 'operator@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Operator');
});

test('first household user command refuses to create a second user', function () {
    User::factory()->create();

    $this->artisan('app:create-first-user', [
        '--name' => 'Second User',
        '--email' => 'second@example.com',
        '--password' => 'password',
    ])->assertFailed();

    expect(User::where('email', 'second@example.com')->exists())->toBeFalse();
});
