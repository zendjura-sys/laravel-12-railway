<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * API для мобільного застосунку (Flutter) — токени Sanctum, без сесії й
 * CSRF. Веб і адмінка й далі йдуть через routes/web.php з сесіями, цей
 * файл існує лише для мобільного клієнта.
 */
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/two-factor', [AuthController::class, 'loginTwoFactor']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
