<?php

namespace Addons\Reports\Http\Controllers;

use Addons\Reports\Events\ReportCreated;
use Addons\Reports\Models\Report;
use Illuminate\Validation\Rule;
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
            'type' => ['required', Rule::in(Report::TYPES)],
            // bizwar — той самий сенс win/loss, що й kapt, тому те саме поле outcome.
            'outcome' => ['required_if:type,kapt,bizwar', 'nullable', 'in:win,loss'],
            'weight' => ['required_if:type,contract', 'nullable', 'in:light,medium,heavy'],
            'amount' => ['required_if:type,investment', 'nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Поле, не относящееся к выбранному типу, всегда обнуляем —
        // иначе в БД могло бы осесть, например, outcome у type=contract.
        if (! in_array($data['type'], ['kapt', 'bizwar'], true)) {
            $data['outcome'] = null;
        }
        if ($data['type'] !== 'contract') {
            $data['weight'] = null;
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
