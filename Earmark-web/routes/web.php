<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BucketController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionSplitController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])
    ->prefix('household')
    ->name('household.')
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
        Route::resource('accounts', AccountController::class)->only(['index', 'store']);
        Route::resource('categories', CategoryController::class)->only(['index', 'store']);
        Route::resource('buckets', BucketController::class)->only(['index', 'store', 'destroy']);
        Route::resource('transactions', TransactionController::class)->only(['index', 'store']);
        Route::post('transactions/{transaction}/split', [TransactionSplitController::class, 'store'])
            ->name('transactions.split.store');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
