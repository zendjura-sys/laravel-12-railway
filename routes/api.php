<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

/**
 * API для мобільного застосунку (Flutter) — токени Sanctum, без сесії й
 * CSRF. Веб і адмінка й далі йдуть через routes/web.php з сесіями, цей
 * файл існує лише для мобільного клієнта.
 */
Route::get('/app-config', [AppConfigController::class, 'show']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/two-factor', [AuthController::class, 'loginTwoFactor']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Мінімальна нативна адмінка (лише ядро, без аддон-специфічних дій) —
    // доступ гейтиться в самому контролері (users.manage / будь-який *.manage).
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/users', [AdminController::class, 'users']);
});
