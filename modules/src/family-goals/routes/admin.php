<?php

use Addons\FamilyGoals\Http\Controllers\Admin\FamilyGoalsAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:goals.manage'])
    ->prefix('admin/family-goals')
    ->name('admin.family-goals.')
    ->group(function () {
        Route::get('/', [FamilyGoalsAdminController::class, 'index'])->name('index');
        Route::post('/', [FamilyGoalsAdminController::class, 'store'])->name('store');
        Route::put('/{familyGoal}/progress', [FamilyGoalsAdminController::class, 'updateProgress'])->name('progress');
        Route::post('/{familyGoal}/close', [FamilyGoalsAdminController::class, 'close'])->name('close');
    });
