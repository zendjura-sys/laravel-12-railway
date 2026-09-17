<?php

namespace Addons\Reports\Http\Controllers\Admin;

use Addons\Reports\Events\ReportReviewed;
use Addons\Reports\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportReviewController
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');

        $reports = Report::query()
            ->with(['user:id,name', 'submitter:id,name', 'reviewer:id,name', 'attachments'])
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'status' => $status,
        ]);
    }

    /**
     * Оцінка ставиться ЛИШЕ при затвердженні — відхилений звіт премії не
     * приносить, оцінювати там нема що.
     */
    public function approve(Request $request, Report $report): JsonResponse
    {
        if (! $report->isPending()) {
            return $this->alreadyReviewed();
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'grade' => ['required', Rule::in(Report::GRADES)],
            'grade_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($data['grade'], Report::LOW_GRADES, true) && trim((string) ($data['grade_reason'] ?? '')) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Для низької оцінки потрібно вказати причину.',
                'data' => null,
                'errors' => ['grade_reason' => ['Вкажіть причину низької оцінки.']],
                'redirect' => null,
            ], 422);
        }

        $report->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
            'grade' => $data['grade'],
            'grade_reason' => $data['grade_reason'] ?? null,
        ]);

        ReportReviewed::dispatch($report->fresh());

        return response()->json([
            'ok' => true,
            'message' => 'Звіт затверджено.',
            'data' => ['report' => $report->fresh()],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function reject(Request $request, Report $report): JsonResponse
    {
        if (! $report->isPending()) {
            return $this->alreadyReviewed();
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $report->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ]);

        ReportReviewed::dispatch($report->fresh());

        return response()->json([
            'ok' => true,
            'message' => 'Звіт відхилено.',
            'data' => ['report' => $report->fresh()],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    private function alreadyReviewed(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Звіт вже розглянуто раніше.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ], 422);
    }
}
