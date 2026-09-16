<?php

namespace Addons\Reports\Http\Controllers\Admin;

use Addons\Reports\Events\ReportReviewed;
use Addons\Reports\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportReviewController
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');

        $reports = Report::query()
            ->with(['user:id,name', 'submitter:id,name', 'reviewer:id,name'])
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'status' => $status,
        ]);
    }

    public function approve(Request $request, Report $report): JsonResponse
    {
        return $this->review($request, $report, 'approved');
    }

    public function reject(Request $request, Report $report): JsonResponse
    {
        return $this->review($request, $report, 'rejected');
    }

    private function review(Request $request, Report $report, string $status): JsonResponse
    {
        if (! $report->isPending()) {
            return response()->json([
                'ok' => false,
                'message' => 'Звіт вже розглянуто раніше.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $report->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('note'),
        ]);

        ReportReviewed::dispatch($report->fresh());

        return response()->json([
            'ok' => true,
            'message' => $status === 'approved' ? 'Звіт затверджено.' : 'Звіт відхилено.',
            'data' => ['report' => $report->fresh()],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
