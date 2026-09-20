<?php

use Addons\Messenger\Http\Controllers\Api\MessengerController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Той самий require-механізм, що й web/admin: модуль сам оголошує свій
// /api-префікс і auth:sanctum — на відміну від core routes/api.php, це
// НЕ проходить через withRouting(api: ...), тому автоматичного префікса
// й "api"-мідлвар-групи тут немає. SubstituteBindings — теж не з
// коробки: без нього {conversation}/{target} у сигнатурі контролера
// мовчки підставляють ПОРОЖНЮ модель замість реальної з БД (замість
// 404/403) — типово в "web"-групі це вже включено за замовчуванням.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class])->group(function () {
    Route::get('/messenger', [MessengerController::class, 'indexJson']);
    Route::get('/messenger/members/search', [MessengerController::class, 'searchMembers']);
    Route::post('/messenger/direct/{target}', [MessengerController::class, 'startDirectJson']);
    Route::get('/messenger/{conversation}', [MessengerController::class, 'showJson']);
    Route::get('/messenger/{conversation}/messages', [MessengerController::class, 'messagesSince']);
    Route::post('/messenger/{conversation}/messages', [MessengerController::class, 'store']);
    Route::post('/messenger/{conversation}/read', [MessengerController::class, 'markRead']);
});
