<?php

use Addons\TelegramBot\Http\Controllers\Admin\TelegramAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:telegram.manage'])
    ->prefix('admin/telegram')
    ->name('admin.telegram.')
    ->group(function () {
        Route::get('/', [TelegramAdminController::class, 'index'])->name('index');
        Route::post('/webhook', [TelegramAdminController::class, 'setupWebhook'])->name('webhook.setup');
        Route::delete('/webhook', [TelegramAdminController::class, 'removeWebhook'])->name('webhook.remove');
        Route::post('/applications/{application}/review', [TelegramAdminController::class, 'review'])
            ->name('applications.review');
    });
