<?php

use Addons\Progression\Http\Controllers\ProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/progress', [ProgressController::class, 'index'])->name('progression.index');
    Route::get('/leaderboard', [ProgressController::class, 'leaderboard'])->name('progression.leaderboard');
});
