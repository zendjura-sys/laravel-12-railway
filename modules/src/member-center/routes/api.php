<?php

use Addons\MemberCenter\Http\Controllers\Admin\DisciplineController as AdminDisciplineController;
use Addons\MemberCenter\Http\Controllers\Admin\MemberController;
use Addons\MemberCenter\Http\Controllers\DisciplineController;
use Addons\MemberCenter\Http\Controllers\Api\AdminLeaveController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Мінімальна адмінка в застосунку: лише заявки на відпустку, що
// очікують рішення. approveLeave()/rejectLeave() — ті самі методи, що й
// на сайті (Admin\MemberController), уже повертають JSON.
// SubstituteBindings обов'язковий: без нього {leaveRequest} мовчки
// підставляє порожню модель замість реальної з БД.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class, 'permission:members.manage'])->group(function () {
    Route::get('/admin/leave-requests/pending', [AdminLeaveController::class, 'pendingJson']);
    Route::post('/admin/leave-requests/{leaveRequest}/approve', [MemberController::class, 'approveLeave']);
    Route::post('/admin/leave-requests/{leaveRequest}/reject', [MemberController::class, 'rejectLeave']);
});

// Покарання: учасник — свій стан і оплата штрафу з рахунку; керівництво
// (members.manage) — ті самі JSON-методи, що й адмінка сайту.
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class])->group(function () {
    Route::get('/discipline', [DisciplineController::class, 'indexJson']);
    Route::post('/discipline/{warning}/pay', [DisciplineController::class, 'pay']);
});
Route::prefix('api')->middleware(['auth:sanctum', SubstituteBindings::class, 'permission:members.manage'])->group(function () {
    Route::get('/admin/discipline', [AdminDisciplineController::class, 'indexJson']);
    Route::get('/admin/discipline/members/search', [AdminDisciplineController::class, 'searchMembers']);
    Route::post('/admin/discipline', [AdminDisciplineController::class, 'store']);
    Route::post('/admin/discipline/{warning}/revoke', [AdminDisciplineController::class, 'revoke']);
    Route::post('/admin/discipline/{warning}/mark-paid', [AdminDisciplineController::class, 'markPaid']);
});
