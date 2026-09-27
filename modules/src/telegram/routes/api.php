<?php

use Addons\TelegramBot\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

// Той самий require-механізм, що й web/admin/events — LinkController уже
// повертає чистий JSON без жодної Inertia-специфіки, тому мобільний
// застосунок (Flutter) використовує ті самі методи, лише під
// auth:sanctum замість сесії.
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/telegram/status', [LinkController::class, 'status']);
    Route::post('/telegram/generate-code', [LinkController::class, 'generateCode']);
    Route::post('/telegram/unlink', [LinkController::class, 'unlink']);
});
