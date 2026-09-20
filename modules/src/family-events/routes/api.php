<?php

use Addons\FamilyEvents\Http\Controllers\Admin\FamilyEventsAdminController;
use Addons\FamilyEvents\Http\Controllers\Api\AdminFamilyEventsController;
use Addons\FamilyEvents\Http\Controllers\Api\FamilyEventsController as ApiFamilyEventsController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Учасник: список майбутніх подій + RSVP. SubstituteBindings обов'язковий
// для {event} — без нього rsvp() мовчки отримав би порожню модель замість
// реальної з БД.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class])->group(function () {
    Route::get('/events', [ApiFamilyEventsController::class, 'indexJson']);
    Route::post('/events/{event}/rsvp', [ApiFamilyEventsController::class, 'rsvpJson']);
});

// Мінімальна адмінка в застосунку: список/створення/видалення подій і
// ручний дайджест. aiDraft() — та сама AI-підказка, що на сайті, уже
// повертає JSON.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class, 'permission:events.manage'])->group(function () {
    Route::get('/admin/events', [AdminFamilyEventsController::class, 'indexJson']);
    Route::post('/admin/events', [AdminFamilyEventsController::class, 'storeJson']);
    Route::delete('/admin/events/{familyEvent}', [AdminFamilyEventsController::class, 'destroyJson']);
    Route::post('/admin/events/send-digest', [AdminFamilyEventsController::class, 'sendDigestJson']);
    Route::post('/admin/events/ai-draft', [FamilyEventsAdminController::class, 'aiDraft']);
});
