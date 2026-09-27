<?php

use Addons\AiAssistant\Http\Controllers\Admin\AiAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:settings.manage'])
    ->prefix('admin/ai')
    ->name('admin.ai.')
    ->group(function () {
        Route::post('/test', [AiAdminController::class, 'test'])->name('test');
    });
