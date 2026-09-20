<?php

use Addons\Messenger\Http\Controllers\MessengerController;
use Illuminate\Support\Facades\Route;

// Цей файл require'иться AddonServiceProvider на кожен HTTP-запит, поки
// модуль активний, — визначаємо маршрути одразу з потрібним middleware.
Route::middleware(['web', 'auth'])->prefix('messenger')->name('messenger.')->group(function () {
    Route::get('/', [MessengerController::class, 'index'])->name('index');
    Route::get('/members/search', [MessengerController::class, 'searchMembers'])->name('members.search');
    Route::post('/direct/{target}', [MessengerController::class, 'startDirect'])->name('direct.start');
    Route::get('/{conversation}', [MessengerController::class, 'show'])->name('show');
    Route::get('/{conversation}/messages', [MessengerController::class, 'messagesSince'])->name('messages');
    Route::post('/{conversation}/messages', [MessengerController::class, 'store'])->name('messages.store');
    Route::post('/{conversation}/read', [MessengerController::class, 'markRead'])->name('read');
});
