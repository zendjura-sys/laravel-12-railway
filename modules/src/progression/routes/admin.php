<?php

use Addons\Progression\Http\Controllers\Admin\ProgressionAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:progression.manage'])
    ->prefix('admin/progression')
    ->name('admin.progression.')
    ->group(function () {
        Route::get('/', [ProgressionAdminController::class, 'index'])->name('index');
        Route::post('/adjust', [ProgressionAdminController::class, 'adjust'])->name('adjust');
    });
