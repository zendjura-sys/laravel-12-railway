<?php

use Addons\Notifications\Http\Controllers\Admin\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:broadcasts.manage'])
    ->prefix('admin/broadcasts')
    ->name('admin.broadcasts.')
    ->group(function () {
        Route::get('/', [BroadcastController::class, 'index'])->name('index');
        Route::post('/', [BroadcastController::class, 'store'])->name('store');
        Route::post('/polish', [BroadcastController::class, 'polish'])->name('polish');
    });
