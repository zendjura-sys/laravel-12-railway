<?php

use Addons\Bonuses\Http\Controllers\Admin\BonusAdminController as WebBonusAdminController;
use Addons\Bonuses\Http\Controllers\Api\Admin\BonusAdminController as ApiBonusAdminController;
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

// Мобільна адмінка премій/бонусів — повний контроль, той самий, що на
// сайті (Admin\BonusAdminController), лише через Sanctum замість сесії.
// Одноклікові дії там уже повертають JSON (спільний контракт), тож
// маршрути нижче для них вказують напряму на веб-контролер; форми
// (нарахування/налаштування/тіри) на сайті йдуть через Inertia-редірект,
// тому для них — окремі JSON-методи в Api\Admin\BonusAdminController.
Route::prefix('api/admin/bonuses')
    ->middleware(['auth:sanctum', SubstituteBindings::class, 'permission:bonuses.manage'])
    ->group(function () {
        Route::get('/', [ApiBonusAdminController::class, 'indexJson']);
        Route::get('/members/search', [WebBonusAdminController::class, 'searchMembers']);
        Route::post('/manual', [ApiBonusAdminController::class, 'storeManualAward']);
        Route::delete('/manual/{manualAward}', [ApiBonusAdminController::class, 'destroyManualAward']);
        Route::put('/settings', [ApiBonusAdminController::class, 'updateSettings']);
        Route::put('/bank-settings', [ApiBonusAdminController::class, 'updateBankSettings']);
        Route::post('/tiers', [ApiBonusAdminController::class, 'storeTier']);
        Route::delete('/tiers/{tier}', [ApiBonusAdminController::class, 'destroyTier']);
        Route::post('/transfers/{transfer}/reverse', [WebBonusAdminController::class, 'reverseTransfer']);
        Route::post('/cash-requests/{cashRequest}/complete', [WebBonusAdminController::class, 'completeCashRequest']);
        Route::post('/cash-requests/{cashRequest}/cancel', [WebBonusAdminController::class, 'cancelCashRequest']);
        Route::post('/payouts/{payout}/mark-paid', [WebBonusAdminController::class, 'markPaid']);
        Route::post('/run-now', [WebBonusAdminController::class, 'runNow']);
    });
