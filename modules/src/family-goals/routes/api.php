<?php

use Addons\FamilyGoals\Http\Controllers\Admin\FamilyGoalsAdminController;
use Addons\FamilyGoals\Http\Controllers\Api\AdminFamilyGoalsController;
use Addons\FamilyGoals\Http\Controllers\Api\FamilyGoalsController as ApiFamilyGoalsController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Учасник: список активних/досягнутих цілей + стрічка активності —
// лише перегляд, без дій.
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('/family-goals', [ApiFamilyGoalsController::class, 'indexJson']);
});

// Мінімальна адмінка в застосунку: список/створення цілей, ручний
// прогрес і закриття без досягнення. updateProgress()/close() — ті самі
// методи, що на сайті (Admin\FamilyGoalsAdminController), уже
// повертають JSON. SubstituteBindings обов'язковий для {familyGoal}.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class, 'permission:goals.manage'])->group(function () {
    Route::get('/admin/family-goals', [AdminFamilyGoalsController::class, 'indexJson']);
    Route::post('/admin/family-goals', [AdminFamilyGoalsController::class, 'storeJson']);
    Route::put('/admin/family-goals/{familyGoal}/progress', [FamilyGoalsAdminController::class, 'updateProgress']);
    Route::post('/admin/family-goals/{familyGoal}/close', [FamilyGoalsAdminController::class, 'close']);
});
