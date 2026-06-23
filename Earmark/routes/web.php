<?php

use App\Http\Middleware\EnsureValidInvite;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

// Invite-only registration: we disabled Fortify's auto-registered register
// routes via config/fortify.php and re-register them here behind
// EnsureValidInvite. A valid invite code (?invite=...) opens the form for
// one session.
Route::middleware(['guest', EnsureValidInvite::class])->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware(['throttle:6,1'])->name('register.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/household/dashboard', 'pages::household.dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
