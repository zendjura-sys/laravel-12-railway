<?php

use Addons\MemberCenter\Http\Controllers\Admin\DisciplineController;
use Addons\MemberCenter\Http\Controllers\Admin\MemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:members.manage'])
    ->prefix('admin/members')
    ->name('admin.members.')
    ->group(function () {
        Route::get('/', [MemberController::class, 'index'])->name('index');
        Route::put('/{user}/status', [MemberController::class, 'updateStatus'])->name('status');
        Route::get('/{user}/notes', [MemberController::class, 'notes'])->name('notes.index');
        Route::post('/{user}/notes', [MemberController::class, 'storeNote'])->name('notes.store');
        Route::get('/{user}/warnings', [MemberController::class, 'warnings'])->name('warnings.index');
        Route::post('/{user}/warnings', [MemberController::class, 'storeWarning'])->name('warnings.store');
        Route::post('/leave-requests/{leaveRequest}/approve', [MemberController::class, 'approveLeave'])->name('leave.approve');
        Route::post('/leave-requests/{leaveRequest}/reject', [MemberController::class, 'rejectLeave'])->name('leave.reject');
    });

Route::middleware(['web', 'auth', 'verified', 'permission:members.manage'])
    ->prefix('admin/discipline')
    ->name('admin.discipline.')
    ->group(function () {
        Route::get('/', [DisciplineController::class, 'index'])->name('index');
        Route::get('/members/search', [DisciplineController::class, 'searchMembers'])->name('members.search');
        Route::post('/', [DisciplineController::class, 'store'])->name('store');
        Route::post('/{warning}/revoke', [DisciplineController::class, 'revoke'])->name('revoke');
        Route::post('/{warning}/mark-paid', [DisciplineController::class, 'markPaid'])->name('mark-paid');
    });
