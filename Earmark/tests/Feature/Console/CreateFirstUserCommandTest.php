<?php

use App\Models\Household;
use App\Models\User;

test('creates first user and household when no users exist', function () {
    expect(User::count())->toBe(0);

    $exitCode = Artisan::call('earmark:create-first-user', [
        '--email' => 'first@example.com',
        '--name' => 'First Owner',
        '--password' => 'password-secret',
    ]);

    expect($exitCode)->toBe(0, Artisan::output());

    $user = User::firstWhere('email', 'first@example.com');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('First Owner')
        ->and($user->email_verified_at)->not->toBeNull();

    expect(Household::count())->toBe(1);
    expect(DB::table('household_members')->where('user_id', $user->id)->count())->toBe(1);

    $household = $user->household();
    expect($household)->not->toBeNull();
    expect($user->ownsHousehold($household))->toBeTrue();
});

test('refuses to run when any user already exists', function () {
    User::factory()->create();

    $exitCode = Artisan::call('earmark:create-first-user', [
        '--email' => 'second@example.com',
        '--name' => 'Second',
        '--password' => 'password-secret',
    ]);

    expect($exitCode)->toBe(1, Artisan::output());
    expect(User::where('email', 'second@example.com')->exists())->toBeFalse();
});

test('rejects invalid email and short password', function () {
    $exitCode = Artisan::call('earmark:create-first-user', [
        '--email' => 'not-an-email',
        '--name' => 'X',
        '--password' => 'short',
    ]);

    expect($exitCode)->toBe(1, Artisan::output());
    expect(User::count())->toBe(0);
});
