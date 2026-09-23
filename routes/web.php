<?php

use App\Http\Controllers\Household\AccountController;
use App\Http\Controllers\Household\DashboardController;
use App\Http\Controllers\Household\MemberController;
use App\Http\Controllers\Household\NetWorthController;
use App\Http\Controllers\Household\PlanController;
use App\Http\Controllers\Household\TransactionController;
use App\Http\Controllers\Household\TransferController;
use App\Http\Middleware\EnsureEmailIsAllowlisted;
use App\Http\Middleware\EnsureValidInvite;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['guest', EnsureValidInvite::class])->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware(['throttle:6,1', EnsureEmailIsAllowlisted::class])->name('register.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/household/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::get('/household/accounts', [AccountController::class, 'index'])->name('household.accounts.index');
    Route::post('/household/accounts', [AccountController::class, 'store'])->name('household.accounts.store');

    Route::get('/household/plan', [PlanController::class, 'index'])->name('household.plan.index');
    Route::post('/household/plan/buckets', [PlanController::class, 'storeBucket'])->name('household.plan.buckets.store');
    Route::post('/household/plan/categories', [PlanController::class, 'storeCategory'])->name('household.plan.categories.store');

    Route::get('/household/transactions', [TransactionController::class, 'index'])->name('household.transactions.index');
    Route::post('/household/transactions', [TransactionController::class, 'store'])->name('household.transactions.store');
    Route::get('/household/transactions/payee-suggestion', [TransactionController::class, 'suggest'])->name('household.transactions.suggest');

    Route::get('/household/transfers', [TransferController::class, 'index'])->name('household.transfers.index');
    Route::post('/household/transfers', [TransferController::class, 'store'])->name('household.transfers.store');
    Route::delete('/household/transfers/{transaction}', [TransferController::class, 'destroy'])->name('household.transfers.destroy');

    Route::get('/household/net-worth', [NetWorthController::class, 'show'])->name('household.net-worth.show');
    Route::post('/household/net-worth/positions', [NetWorthController::class, 'storePosition'])->name('household.net-worth.positions.store');
    Route::post('/household/net-worth/positions/{position}/valuations', [NetWorthController::class, 'storeValuation'])->name('household.net-worth.valuations.store');
    Route::post('/household/net-worth/plan', [NetWorthController::class, 'storePlan'])->name('household.net-worth.plan.store');

    Route::get('/household/members', [MemberController::class, 'index'])->name('household.members.index');
    Route::post('/household/members/invitations', [MemberController::class, 'store'])->name('household.members.invitations.store');
    Route::delete('/household/members/invitations/{invitation:id}', [MemberController::class, 'destroyInvitation'])->name('household.members.invitations.destroy');
});

require __DIR__.'/settings.php';
