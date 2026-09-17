<?php

use Addons\Bonuses\Http\Controllers\Admin\BonusAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:bonuses.manage'])
    ->prefix('admin/bonuses')
    ->name('admin.bonuses.')
    ->group(function () {
        Route::get('/', [BonusAdminController::class, 'index'])->name('index');
        Route::put('/settings', [BonusAdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/tiers', [BonusAdminController::class, 'storeTier'])->name('tiers.store');
        Route::delete('/tiers/{tier}', [BonusAdminController::class, 'destroyTier'])->name('tiers.destroy');
        Route::post('/payouts/{payout}/mark-paid', [BonusAdminController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::post('/run-now', [BonusAdminController::class, 'runNow'])->name('run-now');
    });
