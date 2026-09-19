<?php

use Addons\MemberCenter\Http\Controllers\MemberCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/member-center', [MemberCenterController::class, 'index'])->name('member-center.index');
    Route::post('/member-center/leave', [MemberCenterController::class, 'storeLeave'])->name('member-center.leave.store');
});
