<?php

namespace Addons\Reports\Http\Controllers\Admin;

use Addons\AiAssistant\Models\AiReportReview;
use Addons\AiAssistant\Services\GradeAdvisor;
use Addons\AiAssistant\Services\RejectionAdvisor;
use Addons\Reports\Events\ReportReviewed;
use Addons\Reports\Models\Report;
use App\Models\Setting;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportReviewController
{
    /** @return array{status:string,type:string,from:string,to:string,q:string} */
    private function filtersFrom(Request $request): array
    {
        return [
            'status' => $request->query('status', 'pending'),
            'type' => $request->query('type', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    private function applyFilters(Builder $query, array $f): Builder
    {
        return $query
            ->when(in_array($f['status'], ['pending', 'approved', 'rejected'], true), fn ($qq) => $qq->where('status', $f['status']))
            ->when(in_array($f['type'], Report::TYPES, true), fn ($qq) => $qq->where('type', $f['type']))
            ->when($f['from'] !== '', fn ($qq) => $qq->whereDate('report_date', '>=', $f['from']))
            ->when($f['to'] !== '', fn ($qq) => $qq->whereDate('report_date', '<=', $f['to']))
            ->when($f['q'] !== '', fn ($qq) => $qq->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$f['q']}%")));
    }

    public function index(Request $request): Response
    {
        $filters = $this->filtersFrom($request);

        $reports = $this->applyFilters(Report::query(), $filters)
            ->with(['user:id,name,gender', 'submitter:id,name,gender', 'reviewer:id,name,gender', 'attachments'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $this->attachAiReviews($reports);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'status' => $filters['status'],
            'filters' => $filters,
            'types' => Report::TYPES,
            'aiRejectionAdviceEnabled' => class_exists(RejectionAdvisor::class) && Setting::get('ai_rejection_advice_enabled') === '1',
            'aiGradeAdviceEnabled' => class_exists(GradeAdvisor::class) && Setting::get('ai_grade_advice_enabled') === '1',
        ]);
    }

    /** Той самий фільтр, що й на екрані — експортується САМЕ те, що адмін бачить, а не все підряд. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filtersFrom($request);

        $reports = $this->applyFilters(Report::query(), $filters)
            ->with(['user:id,name', 'submitter:id,name'])
            ->latest()
            ->get();

        $typeLabels = ['kapt' => 'Капт', 'contract' => 'Контракт', 'bizwar' => 'Бізвар', 'investment' => 'Інвестиції', 'other' => 'Інше'];
        $statusLabels = ['pending' => 'На розгляді', 'approved' => 'Затверджено', 'rejected' => 'Відхилено'];

        $rows = $reports->map(fn (Report $r) => [
            $r->id,
            $r->user?->name ?? '—',
            $r->submitter?->name ?? '—',
            $typeLabels[$r->type] ?? $r->type,
            $statusLabels[$r->status] ?? $r->status,
            $r->report_date?->format('d.m.Y') ?? '',
            $r->wins_count,
            $r->losses_count,
            $r->light_count,
            $r->medium_count,
            $r->heavy_count,
            $r->amount,
            $r->grade,
            $r->created_at?->format('d.m.Y H:i'),
        ]);

        return CsvExport::stream('reports.csv', [
            'ID', 'Учасник', 'Подав', 'Тип', 'Статус', 'Дата звіту',
            'Перемоги', 'Поразки', 'Легкі', 'Середні', 'Важкі', 'Сума', 'Оцінка', 'Подано',
        ], $rows);
    }

    /**
     * AI-assistant — опційна залежність (class_exists, як і скрізь у
     * проєкті): якщо модуль не встановлено чи звіти ще не проаналізовано
     * (черга не встигла чи тумблер вимкнено), просто немає ai_review в
     * даних, і фронт про це вже знає (v-if).
     */
    private function attachAiReviews($reports): void
    {
        if (! class_exists(AiReportReview::class)) {
            return;
        }

        $reviews = AiReportReview::query()
            ->whereIn('report_id', $reports->pluck('id'))
            ->get()
            ->keyBy('report_id');

        $reports->getCollection()->transform(function (Report $report) use ($reviews) {
            $report->setAttribute('ai_review', $reviews->get($report->id));

            return $report;
        });
    }

    /**
     * Готує чернетку пояснення для учасника — адмін бачить її в полі
     * причини відхилення й редагує/прибирає перед тим, як реально
     * натиснути "Відхилити". Сам виклик нічого не міняє в статусі звіту.
     */
    public function aiRecommendation(Request $request, Report $report): JsonResponse
    {
        if (! class_exists(RejectionAdvisor::class)) {
            return response()->json([
                'ok' => false,
                'message' => 'Модуль AI Assistant не встановлено.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $data = $request->validate(['hint' => ['nullable', 'string', 'max:1000']]);

        $text = app(RejectionAdvisor::class)->draft($report, $data['hint'] ?? null);

        return response()->json([
            'ok' => $text !== null,
            'message' => $text !== null ? null : 'Не вдалося згенерувати рекомендацію — перевірте налаштування AI.',
            'data' => ['text' => $text],
            'errors' => null,
            'redirect' => null,
        ], $text !== null ? 200 : 422);
    }

    /**
     * Стартова оцінка-підказка у вікні вибору оцінки — адмін бачить
     * рекомендовану літеру й пояснення, але сам клікає потрібну кнопку
     * (ту саму чи іншу) і сам підтверджує затвердження. Нічого не міняє
     * в звіті сам по собі.
     */
    public function aiGradeRecommendation(Request $request, Report $report): JsonResponse
    {
        if (! class_exists(GradeAdvisor::class)) {
            return response()->json([
                'ok' => false,
                'message' => 'Модуль AI Assistant не встановлено.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $suggestion = app(GradeAdvisor::class)->suggest($report);

        return response()->json([
            'ok' => $suggestion !== null,
            'message' => $suggestion !== null ? null : 'Не вдалося отримати рекомендацію — перевірте налаштування AI.',
            'data' => $suggestion,
            'errors' => null,
            'redirect' => null,
        ], $suggestion !== null ? 200 : 422);
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
