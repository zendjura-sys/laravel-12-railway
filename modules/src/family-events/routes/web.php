<?php

use Addons\FamilyEvents\Http\Controllers\FamilyEventsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/events', [FamilyEventsController::class, 'index'])->name('family-events.index');
});
