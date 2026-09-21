<?php

use Addons\Progression\Http\Controllers\Api\ProgressController;
use Illuminate\Support\Facades\Route;

// Той самий ручний префікс /api, що й у Bonuses/routes/api.php — цей
// файл не проходить через withRouting(api: ...), тому автопрефікса нема.
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/progress', [ProgressController::class, 'indexJson']);
    Route::get('/leaderboard', [ProgressController::class, 'leaderboardJson']);
    Route::get('/hall-of-fame', [ProgressController::class, 'hallOfFameJson']);
});
