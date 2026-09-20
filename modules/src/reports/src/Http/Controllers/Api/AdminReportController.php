<?php

namespace Addons\Reports\Http\Controllers\Api;

use Addons\Reports\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Мобільний двійник Admin\ReportReviewController — лише список на
 * розгляді (без фільтрів/експорту/AI-підказок веб-версії, це мінімальна
 * адмінка в застосунку). approve()/reject() не дублюються тут: маршрути
 * /api вказують напряму на ReportReviewController — ті методи вже
 * повертають чистий JSON.
 */
class AdminReportController
{
    public function pendingJson(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('status', 'pending')
            ->with(['user:id,name', 'submitter:id,name', 'attachments'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'userName' => $r->user?->name,
                'submitterName' => $r->submitter?->name,
                'reportDate' => $r->report_date?->toDateString(),
                'winsCount' => $r->wins_count,
                'lossesCount' => $r->losses_count,
                'lightCount' => $r->light_count,
                'mediumCount' => $r->medium_count,
                'heavyCount' => $r->heavy_count,
                'amount' => $r->amount,
                'description' => $r->description,
                'photoCount' => $r->attachments->count(),
                'createdAt' => $r->created_at,
            ]);

        return response()->json(['reports' => $reports]);
    }
}
