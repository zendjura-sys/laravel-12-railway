<?php

use Addons\Bonuses\Http\Controllers\Api\BankController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// На відміну від core routes/api.php, цей файл підключається напряму
// через require (AddonAutoloader::loadEntrypoint), а не через
// withRouting(api: ...) — тому автоматичного префіксу /api й "api"
// мідлвар-групи тут немає, прописуємо вручну. SubstituteBindings теж
// довелось додати явно: без нього {deposit} нижче мовчки підставляв
// ПОРОЖНЮ модель замість реальної з БД (withdrawDeposit() завжди
// відповідав 403, бо $deposit->user_id був null) — у "web"-групі це
// вже включено за замовчуванням, тут — ні.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class])->group(function () {
    Route::get('/bank', [BankController::class, 'index']);
    Route::get('/bank/recipients/search', [BankController::class, 'searchRecipients']);
    Route::post('/bank/transfer', [BankController::class, 'storeTransfer']);
    Route::post('/bank/deposits', [BankController::class, 'storeDeposit']);
    Route::post('/bank/deposits/{deposit}/withdraw', [BankController::class, 'withdrawDeposit']);
    Route::post('/bank/cash-requests', [BankController::class, 'storeCashRequest']);
});
