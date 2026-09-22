<?php

use Addons\Messenger\Http\Controllers\MessengerController;
use Illuminate\Support\Facades\Route;

// Цей файл require'иться AddonServiceProvider на кожен HTTP-запит, поки
// модуль активний, — визначаємо маршрути одразу з потрібним middleware.
Route::middleware(['web', 'auth'])->prefix('messenger')->name('messenger.')->group(function () {
    Route::get('/', [MessengerController::class, 'index'])->name('index');
    Route::get('/members/search', [MessengerController::class, 'searchMembers'])->name('members.search');
    Route::post('/direct/{target}', [MessengerController::class, 'startDirect'])->name('direct.start');
    // Літеральні шляхи — перед {conversation}, інакше wildcard перехопить їх.
    Route::get('/stickers', [MessengerController::class, 'stickers'])->name('stickers.index');
    Route::post('/stickers', [MessengerController::class, 'storeSticker'])->name('stickers.store');
    Route::delete('/stickers/{sticker}', [MessengerController::class, 'destroySticker'])->name('stickers.destroy');
    Route::get('/gifs/search', [MessengerController::class, 'searchGifs'])->name('gifs.search');
    Route::get('/{conversation}', [MessengerController::class, 'show'])->name('show');
    Route::get('/{conversation}/messages', [MessengerController::class, 'messagesSince'])->name('messages');
    Route::get('/{conversation}/members', [MessengerController::class, 'members'])->name('members');
    Route::post('/{conversation}/messages', [MessengerController::class, 'store'])->name('messages.store');
    Route::delete('/{conversation}/messages/{message}', [MessengerController::class, 'destroyMessage'])->name('messages.destroy');
    Route::post('/{conversation}/read', [MessengerController::class, 'markRead'])->name('read');
});
