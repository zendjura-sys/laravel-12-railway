<?php

namespace Addons\Reports\Http\Controllers\Api;

use Addons\Reports\Events\ReportCreated;
use Addons\Reports\Models\Report;
use Addons\Reports\Models\ReportAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Мобільний двійник Http\Controllers\ReportController — та сама
 * валідація й побічні ефекти (ReportCreated, вкладення фото), лише
 * подання "за друга" тут немає (v1 застосунку — тільки за себе) і
 * розклад капта теж (окрема, більш "веб" функція).
 */
class ReportController
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->with('attachments')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'grade' => $r->grade,
                'review_note' => $r->review_note,
                'report_date' => $r->report_date,
                'wins_count' => $r->wins_count,
                'losses_count' => $r->losses_count,
                'kapt_times' => $r->kapt_times,
                'light_count' => $r->light_count,
                'medium_count' => $r->medium_count,
                'heavy_count' => $r->heavy_count,
                'amount' => $r->amount,
                'description' => $r->description,
                'photo_count' => $r->attachments->count(),
                'photos' => $r->attachments->map(fn (ReportAttachment $a) => $a->url)->values(),
                'created_at' => $r->created_at,
            ]);

        return response()->json(['reports' => $reports]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Report::SUBMITTABLE_TYPES)],
            'report_date' => ['required_if:type,bizwar,contract', 'nullable', 'date'],
            'wins_count' => ['required_if:type,bizwar', 'nullable', 'integer', 'min:0'],
            'losses_count' => ['required_if:type,bizwar', 'nullable', 'integer', 'min:0'],
            'kapt_times' => ['nullable', 'array'],
            'kapt_times.*' => ['string', Rule::in(Report::KAPT_TIMES)],
            'light_count' => ['required_if:type,contract', 'nullable', 'integer', 'min:0'],
            'medium_count' => ['required_if:type,contract', 'nullable', 'integer', 'min:0'],
            'heavy_count' => ['required_if:type,contract', 'nullable', 'integer', 'min:0'],
            'amount' => ['required_if:type,investment', 'nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:30'],
            'photos.*' => ['file', 'mimes:jpeg,jpg,png,webp', 'max:20480'],
        ]);

        if ($data['type'] === 'bizwar') {
            if ((int) ($data['wins_count'] ?? 0) + (int) ($data['losses_count'] ?? 0) === 0) {
                throw ValidationException::withMessages(['wins_count' => 'Вкажіть хоча б одну перемогу або поразку.']);
            }
            if (empty($data['kapt_times'])) {
                throw ValidationException::withMessages(['kapt_times' => 'Виберіть хоча б один час капта.']);
            }
        }

        if ($data['type'] === 'contract') {
            $total = (int) ($data['light_count'] ?? 0) + (int) ($data['medium_count'] ?? 0) + (int) ($data['heavy_count'] ?? 0);
            if ($total === 0) {
                throw ValidationException::withMessages(['light_count' => 'Вкажіть хоча б один контракт.']);
            }
        }

        if (! in_array($data['type'], ['bizwar', 'contract'], true)) {
            $data['report_date'] = null;
        }
        if ($data['type'] !== 'bizwar') {
            $data['wins_count'] = null;
            $data['losses_count'] = null;
            $data['kapt_times'] = null;
        }
        if ($data['type'] !== 'contract') {
            $data['light_count'] = null;
            $data['medium_count'] = null;
            $data['heavy_count'] = null;
        }
        if ($data['type'] !== 'investment') {
            $data['amount'] = null;
        }

        $photos = $data['photos'] ?? [];
        unset($data['photos']);

        $report = Report::create([
            ...$data,
            'user_id' => $request->user()->id,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        foreach (array_values($photos) as $i => $photo) {
            $path = $photo->store('reports/attachments', 'public');

            ReportAttachment::create([
                'report_id' => $report->id,
                'disk_path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'size' => $photo->getSize(),
                'position' => $i,
            ]);
        }

        ReportCreated::dispatch($report);

        return response()->json(['message' => 'Звіт подано, очікує на модерацію.', 'id' => $report->id]);
    }

    /**
     * Видалення власних звітів (мобільний режим вибору): {ids: [...]} чи
     * {all: true}. Лише ті, що ще на розгляді або відхилені: затверджений
     * звіт уже нарахував досвід, премії й прогрес цілей родини — його
     * видалення "заднім числом" змінило б гроші й рейтинги, тож такі
     * звіти пропускаються (skipped у відповіді).
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'ids' => ['required_without:all', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $reports = Report::query()
            ->with('attachments')
            ->where('user_id', $request->user()->id)
            ->when(! ($data['all'] ?? false), fn ($q) => $q->whereIn('id', $data['ids'] ?? []))
            ->get();

        $deletable = $reports->whereIn('status', ['pending', 'rejected']);
        $paths = $deletable->flatMap(fn (Report $r) => $r->attachments->pluck('disk_path'))->filter()->values();

        DB::transaction(fn () => Report::query()->whereIn('id', $deletable->pluck('id'))->delete());
        // Файли — після коміту: відкат транзакції не повинен лишити звіт без фото.
        if ($paths->isNotEmpty()) {
            Storage::disk('public')->delete($paths->all());
        }

        $skipped = $reports->count() - $deletable->count();

        return response()->json([
            'ok' => true,
            'message' => $skipped > 0
                ? "Затверджені звіти ({$skipped}) не видаляються — за них уже нараховано досвід і премії."
                : null,
            'data' => ['deleted' => $deletable->count(), 'skipped' => $skipped],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
