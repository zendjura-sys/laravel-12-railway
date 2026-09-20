<?php

use Addons\Reports\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// Ручний префікс /api, як і в інших модулів — цей файл вантажиться через
// require (AddonAutoloader::loadEntrypoint), а не withRouting(api: ...).
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
});
