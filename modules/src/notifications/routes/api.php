<?php

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
