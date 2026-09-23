<?php

use App\Http\Controllers\Household\AccountController;
use App\Http\Controllers\Household\DashboardController;
use App\Http\Controllers\Household\ImportController;
use App\Http\Controllers\Household\MemberController;
use App\Http\Controllers\Household\NetWorthController;
use App\Http\Controllers\Household\PayeeRuleController;
use App\Http\Controllers\Household\PlanController;
use App\Http\Controllers\Household\ReconciliationController;
use App\Http\Controllers\Household\RecurringController;
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
    Route::post('/household/accounts/reorder', [AccountController::class, 'reorder'])->name('household.accounts.reorder');
    Route::patch('/household/accounts/{account}', [AccountController::class, 'update'])->name('household.accounts.update');
    Route::post('/household/accounts/{account}/archive', [AccountController::class, 'archive'])->name('household.accounts.archive');
    Route::post('/household/accounts/{account}/restore', [AccountController::class, 'restore'])->name('household.accounts.restore');

    Route::get('/household/plan', [PlanController::class, 'index'])->name('household.plan.index');
    Route::post('/household/plan/buckets', [PlanController::class, 'storeBucket'])->name('household.plan.buckets.store');
    Route::post('/household/plan/categories', [PlanController::class, 'storeCategory'])->name('household.plan.categories.store');
    Route::post('/household/plan/assign', [PlanController::class, 'assign'])->name('household.plan.assign');
    Route::post('/household/plan/buckets/{bucket}/obligation', [PlanController::class, 'setObligation'])->name('household.plan.buckets.obligation');

    Route::get('/household/transactions', [TransactionController::class, 'index'])->name('household.transactions.index');
    Route::post('/household/transactions', [TransactionController::class, 'store'])->name('household.transactions.store');
    Route::get('/household/transactions/payee-suggestion', [TransactionController::class, 'suggest'])->name('household.transactions.suggest');
    Route::post('/household/transactions/review', [TransactionController::class, 'review'])->name('household.transactions.review');
    Route::patch('/household/transactions/{transaction}', [TransactionController::class, 'update'])->name('household.transactions.update');
    Route::post('/household/transactions/{transaction}/split', [TransactionController::class, 'split'])->name('household.transactions.split');
    Route::delete('/household/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('household.transactions.destroy');

    Route::get('/household/import', [ImportController::class, 'index'])->name('household.import.index');
    Route::post('/household/import', [ImportController::class, 'store'])->name('household.import.store');
    Route::get('/household/import/{statementUpload}/review', [ImportController::class, 'review'])->name('household.import.review');
    Route::patch('/household/import/{statementUpload}/staged', [ImportController::class, 'updateStaged'])->name('household.import.staged.update');
    Route::post('/household/import/{statementUpload}/promote', [ImportController::class, 'promote'])->name('household.import.promote');

    Route::get('/household/rules', [PayeeRuleController::class, 'index'])->name('household.rules.index');
    Route::post('/household/rules', [PayeeRuleController::class, 'store'])->name('household.rules.store');
    Route::post('/household/rules/preview', [PayeeRuleController::class, 'preview'])->name('household.rules.preview');
    Route::post('/household/rules/reorder', [PayeeRuleController::class, 'reorder'])->name('household.rules.reorder');
    Route::patch('/household/rules/{payeeRule}', [PayeeRuleController::class, 'update'])->name('household.rules.update');
    Route::delete('/household/rules/{payeeRule}', [PayeeRuleController::class, 'destroy'])->name('household.rules.destroy');
    Route::post('/household/rules/{payeeRule}/apply', [PayeeRuleController::class, 'apply'])->name('household.rules.apply');

    Route::get('/household/transfers', [TransferController::class, 'index'])->name('household.transfers.index');
    Route::post('/household/transfers', [TransferController::class, 'store'])->name('household.transfers.store');
    Route::patch('/household/transfers/{transaction}', [TransferController::class, 'update'])->name('household.transfers.update');
    Route::delete('/household/transfers/{transaction}', [TransferController::class, 'destroy'])->name('household.transfers.destroy');

    Route::get('/household/recurring', [RecurringController::class, 'index'])->name('household.recurring.index');
    Route::post('/household/recurring', [RecurringController::class, 'store'])->name('household.recurring.store');
    Route::patch('/household/recurring/{recurringSchedule}', [RecurringController::class, 'update'])->name('household.recurring.update');

    Route::get('/household/reconcile', [ReconciliationController::class, 'index'])->name('household.reconcile.index');
    Route::post('/household/reconcile/preview', [ReconciliationController::class, 'preview'])->name('household.reconcile.preview');
    Route::post('/household/reconcile', [ReconciliationController::class, 'store'])->name('household.reconcile.store');

    Route::get('/household/net-worth', [NetWorthController::class, 'show'])->name('household.net-worth.show');
    Route::post('/household/net-worth/positions', [NetWorthController::class, 'storePosition'])->name('household.net-worth.positions.store');
    Route::post('/household/net-worth/positions/{position}/valuations', [NetWorthController::class, 'storeValuation'])->name('household.net-worth.valuations.store');
    Route::patch('/household/net-worth/valuations/{valuation}', [NetWorthController::class, 'updateValuation'])->name('household.net-worth.valuations.update');
    Route::post('/household/net-worth/valuations/{valuation}/archive', [NetWorthController::class, 'archiveValuation'])->name('household.net-worth.valuations.archive');
    Route::post('/household/net-worth/plan', [NetWorthController::class, 'storePlan'])->name('household.net-worth.plan.store');

    Route::get('/household/members', [MemberController::class, 'index'])->name('household.members.index');
    Route::post('/household/members/invitations', [MemberController::class, 'store'])->name('household.members.invitations.store');
    Route::delete('/household/members/invitations/{invitation:id}', [MemberController::class, 'destroyInvitation'])->name('household.members.invitations.destroy');
});

require __DIR__.'/settings.php';
