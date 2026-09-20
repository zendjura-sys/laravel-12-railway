<?php

use Addons\Bonuses\Http\Controllers\BonusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/bonuses', [BonusController::class, 'index'])->name('bonuses.index');
    Route::get('/bonuses/recipients/search', [BonusController::class, 'searchRecipients'])->name('bonuses.recipients.search');
    Route::post('/bonuses/transfer', [BonusController::class, 'storeTransfer'])->name('bonuses.transfer');
    Route::post('/bonuses/deposits', [BonusController::class, 'storeDeposit'])->name('bonuses.deposits.store');
    Route::post('/bonuses/deposits/{deposit}/withdraw', [BonusController::class, 'withdrawDeposit'])->name('bonuses.deposits.withdraw');
});
