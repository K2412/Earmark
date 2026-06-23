<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('household/dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
