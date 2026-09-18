<?php

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
