<?php

use Addons\FamilyEvents\Http\Controllers\Admin\FamilyEventsAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:events.manage'])
    ->prefix('admin/family-events')
    ->name('admin.family-events.')
    ->group(function () {
        Route::get('/', [FamilyEventsAdminController::class, 'index'])->name('index');
        Route::post('/', [FamilyEventsAdminController::class, 'store'])->name('store');
        Route::delete('/{familyEvent}', [FamilyEventsAdminController::class, 'destroy'])->name('destroy');
    });
