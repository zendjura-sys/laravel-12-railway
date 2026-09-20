<?php

use Addons\Reports\Http\Controllers\Admin\ReportReviewController;
use Addons\Reports\Http\Controllers\Api\AdminReportController;
use Addons\Reports\Http\Controllers\Api\ReportController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Ручний префікс /api, як і в інших модулів — цей файл вантажиться через
// require (AddonAutoloader::loadEntrypoint), а не withRouting(api: ...).
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
});

// Мінімальна адмінка в застосунку: лише розгляд звітів на очікуванні.
// approve()/reject() — ті самі методи, що й на сайті (Admin\ReportReviewController),
// уже повертають JSON, тому просто ще один маршрут на той самий клас.
// SubstituteBindings обов'язковий: без нього {report} мовчки підставляє
// порожню модель замість реальної з БД.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class, 'permission:reports.manage'])->group(function () {
    Route::get('/admin/reports/pending', [AdminReportController::class, 'pendingJson']);
    Route::post('/admin/reports/{report}/approve', [ReportReviewController::class, 'approve']);
    Route::post('/admin/reports/{report}/reject', [ReportReviewController::class, 'reject']);
    // AI Assistant (опційно) — ті самі підказки, що на сайті: чернетка
    // пояснення при відхиленні, рекомендована оцінка при затвердженні.
    // Обидва методи вже самі повертають 422 з поясненням, якщо AI
    // Assistant не встановлено — окремої перевірки тут не треба.
    Route::post('/admin/reports/{report}/ai-recommendation', [ReportReviewController::class, 'aiRecommendation']);
    Route::post('/admin/reports/{report}/ai-grade-recommendation', [ReportReviewController::class, 'aiGradeRecommendation']);
});
