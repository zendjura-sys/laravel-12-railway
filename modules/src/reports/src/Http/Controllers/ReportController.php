<?php

namespace Addons\Reports\Http\Controllers;

use Addons\Reports\Events\ReportCreated;
use Addons\Reports\Models\Report;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
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
        // Свої звіти (де я суб'єкт) + ті, що я подав за друзів (де я
        // submitted_by, а суб'єкт — хтось інший) — інакше подання "за
        // друга" одразу зникало б з очей того, хто його реально відправив.
        $reports = Report::query()
            ->with(['user:id,name', 'submitter:id,name'])
            ->where(fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->orWhere('submitted_by', $request->user()->id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'myId' => $request->user()->id,
        ]);
    }

    /**
     * Пошук для перемикача "за друга" — шукає серед РЕАЛЬНИХ (не тіньових)
     * акаунтів за іменем/прізвищем, і серед уже заведених тіньових теж:
     * якщо хтось уже подавав за цього ж друга раніше, другий раз створювати
     * дубль тіньового акаунту не треба, треба знайти вже наявний.
     */
    public function searchMembers(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['ok' => true, 'message' => null, 'data' => ['members' => []], 'errors' => null, 'redirect' => null]);
        }

        $members = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'is_shadow']);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['members' => $members],
            'errors' => null,
            'redirect' => null,
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
            'subject_type' => ['required', Rule::in(['self', 'friend'])],
            'subject_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject_first_name' => ['required_if:subject_type,friend', 'nullable', 'string', 'max:120'],
            'subject_last_name' => ['nullable', 'string', 'max:120'],
        ], [], ['subject_first_name' => "ім'я друга"]);

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

        $subject = $this->resolveSubject($request, $data);

        unset($data['subject_type'], $data['subject_user_id'], $data['subject_first_name'], $data['subject_last_name']);

        $report = Report::create([
            ...$data,
            'user_id' => $subject->id,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        ReportCreated::dispatch($report);

        return back()->with('success', 'Звіт подано, очікує на модерацію.');
    }

    /**
     * "За себе" — завжди поточний юзер. "За друга" — або вже обраний
     * (subject_user_id, реальний чи вже раніше заведений тіньовий), або
     * нове ім'я: спершу шукаємо точний збіг (щоб не плодити дублі
     * тіньових акаунтів на одного й того ж друга), і тільки якщо нема —
     * заводимо новий тіньовий акаунт.
     */
    private function resolveSubject(Request $request, array $data): User
    {
        if ($data['subject_type'] === 'self') {
            return $request->user();
        }

        if (! empty($data['subject_user_id'])) {
            return User::findOrFail($data['subject_user_id']);
        }

        $fullName = trim($data['subject_first_name'].' '.($data['subject_last_name'] ?? ''));

        return User::where('name', $fullName)->first()
            ?? User::createShadow($data['subject_first_name'], $data['subject_last_name'] ?? null);
    }
}
