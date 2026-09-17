<?php

namespace Addons\Reports\Http\Controllers;

use Addons\Reports\Events\ReportCreated;
use Addons\Reports\Models\Report;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Публичная (для авторизованных участников) сторона модуля: подать отчёт,
 * посмотреть свою историю. Никакой модерации здесь — только Admin-контроллер.
 */
class ReportController
{
    public function index(Request $request): Response
    {
        $reports = Report::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Report::SUBMITTABLE_TYPES)],
            // Дата події, а не подачі — бізвар і контракти звітують пакетом
            // "скільки набралось за дату", а не по одному на подію.
            'report_date' => ['required_if:type,bizwar,contract', 'nullable', 'date'],
            'wins_count' => ['required_if:type,bizwar', 'nullable', 'integer', 'min:0'],
            'losses_count' => ['required_if:type,bizwar', 'nullable', 'integer', 'min:0'],
            // Фіксується поіменно (не просто кількість), щоб майбутній
            // модуль автопідрахунку премій міг рахувати по конкретних годинах.
            //
            // "min:1" тут НЕ вішаємо: форма завжди шле kapt_times як масив
            // (хай навіть порожній) незалежно від обраного типу — nullable
            // звільняє лише null, а не порожній масив, тож при поданні
            // контракту чи інвестиції порожній [] ламав би валідацію.
            // "Хоча б один час" для bizwar перевіряється нижче окремо.
            'kapt_times' => ['nullable', 'array'],
            'kapt_times.*' => ['string', Rule::in(Report::KAPT_TIMES)],
            'light_count' => ['required_if:type,contract', 'nullable', 'integer', 'min:0'],
            'medium_count' => ['required_if:type,contract', 'nullable', 'integer', 'min:0'],
            'heavy_count' => ['required_if:type,contract', 'nullable', 'integer', 'min:0'],
            'amount' => ['required_if:type,investment', 'nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
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

        // Поле, не относящееся к выбранному типу, всегда обнуляем —
        // иначе в БД могло бы осесть, например, amount у type=contract.
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

        $report = Report::create([
            ...$data,
            'user_id' => $request->user()->id,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        ReportCreated::dispatch($report);

        return back()->with('success', 'Звіт подано, очікує на модерацію.');
    }
}
