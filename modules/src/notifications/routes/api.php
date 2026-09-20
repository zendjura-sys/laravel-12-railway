<?php

use Addons\Notifications\Http\Controllers\Api\AdminBroadcastController;
use Addons\Notifications\Http\Controllers\Api\NotificationController as ApiNotificationController;
use Addons\Notifications\Http\Controllers\NotificationController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// markRead()/markAllRead() з веб-контролера вже повертають чистий JSON —
// reuse напряму, без дублювання. SubstituteBindings обов'язковий: без
// нього {notification} мовчки підставляє порожню модель (і markRead
// завжди відповідав би 403, бо notification->user_id був би null).
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class])->group(function () {
    Route::get('/notifications', [ApiNotificationController::class, 'indexJson']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
});

// Мінімальна адмінка в застосунку: написати й надіслати розсилку.
// polish() — та сама AI-підказка, що на сайті, уже повертає JSON.
Route::prefix('api')->middleware(['auth:sanctum', 'permission:broadcasts.manage'])->group(function () {
    Route::get('/admin/broadcasts/audience-options', [AdminBroadcastController::class, 'audienceOptionsJson']);
    Route::get('/admin/broadcasts/recent', [AdminBroadcastController::class, 'recentJson']);
    Route::post('/admin/broadcasts', [AdminBroadcastController::class, 'storeJson']);
    Route::post('/admin/broadcasts/polish', [AdminBroadcastController::class, 'polish']);
});
