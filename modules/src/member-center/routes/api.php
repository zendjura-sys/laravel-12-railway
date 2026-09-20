<?php

use Addons\MemberCenter\Http\Controllers\Admin\MemberController;
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
