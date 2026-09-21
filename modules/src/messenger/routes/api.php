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
    // Наскрізне шифрування (Фаза 1: direct-розмови) — обмін публічними
    // X25519-ключами. /identity-key публікує СВІЙ ключ, /users/{user}/…
    // читає ЧУЖИЙ.
    Route::post('/messenger/identity-key', [MessengerController::class, 'publishIdentityKey']);
    Route::get('/messenger/users/{user}/identity-key', [MessengerController::class, 'identityKey']);
    Route::post('/messenger/direct/{target}', [MessengerController::class, 'startDirectJson']);
    Route::get('/messenger/stickers', [MessengerController::class, 'stickers']);
    Route::post('/messenger/stickers', [MessengerController::class, 'storeSticker']);
    Route::delete('/messenger/stickers/{sticker}', [MessengerController::class, 'destroySticker']);
    Route::get('/messenger/gifs/search', [MessengerController::class, 'searchGifs']);
    Route::get('/messenger/{conversation}', [MessengerController::class, 'showJson']);
    Route::get('/messenger/{conversation}/messages', [MessengerController::class, 'messagesSince']);
    Route::post('/messenger/{conversation}/messages', [MessengerController::class, 'store']);
    Route::delete('/messenger/{conversation}/messages/{message}', [MessengerController::class, 'destroyMessage']);
    Route::post('/messenger/{conversation}/read', [MessengerController::class, 'markRead']);
});
