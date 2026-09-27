<?php

use Addons\TelegramBot\Http\Controllers\LinkController;
use Addons\TelegramBot\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// БЕЗ 'web' middleware навмисно — сюди стукає лише сервер Telegram
// (без сесії/cookie), і CSRF-перевірка тут не потрібна й лише заважала б.
Route::post('/telegram/webhook/{secret}', WebhookController::class)->name('telegram.webhook');

Route::middleware(['web', 'auth'])->prefix('telegram')->name('telegram.')->group(function () {
    Route::get('/status', [LinkController::class, 'status'])->name('status');
    Route::post('/generate-code', [LinkController::class, 'generateCode'])->name('generate-code');
    Route::post('/unlink', [LinkController::class, 'unlink'])->name('unlink');
});
