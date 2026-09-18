<?php

use Addons\Reports\Http\Controllers\Admin\ReportReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'permission:reports.manage'])
    ->prefix('admin/reports')
    ->name('admin.reports.')
    ->group(function () {
        Route::get('/', [ReportReviewController::class, 'index'])->name('index');
        Route::post('/{report}/approve', [ReportReviewController::class, 'approve'])->name('approve');
        Route::post('/{report}/reject', [ReportReviewController::class, 'reject'])->name('reject');
        Route::post('/{report}/ai-recommendation', [ReportReviewController::class, 'aiRecommendation'])->name('ai-recommendation');
    });
