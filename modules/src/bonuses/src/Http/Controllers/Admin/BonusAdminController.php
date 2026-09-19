<?php

namespace Addons\Bonuses\Http\Controllers\Admin;

use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\BonusSettings;
use Addons\Bonuses\Models\InvestmentAchievementTier;
use Addons\Bonuses\Services\BonusCalculator;
use Addons\Bonuses\Services\BonusDigest;
use App\Support\CsvExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BonusAdminController
{
    /** @return array{from:string,to:string,paid:string} */
    private function filtersFrom(Request $request): array
    {
        return [
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
            'paid' => $request->query('paid', ''),
        ];
    }

    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $f): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->when($f['from'] !== '', fn ($qq) => $qq->whereDate('week_start', '>=', $f['from']))
            ->when($f['to'] !== '', fn ($qq) => $qq->whereDate('week_start', '<=', $f['to']))
            ->when($f['paid'] === '1', fn ($qq) => $qq->where('paid', true))
            ->when($f['paid'] === '0', fn ($qq) => $qq->where('paid', false));
    }

    public function index(Request $request): Response
    {
        $filters = $this->filtersFrom($request);

        $payouts = $this->applyFilters(BonusPayout::query(), $filters)
            ->with('user:id,name')
            ->orderByDesc('week_start')
            ->orderByDesc('total_amount')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Bonuses/Index', [
            'settings' => BonusSettings::current(),
            'tiers' => InvestmentAchievementTier::query()->orderBy('sort_order')->orderBy('threshold_amount')->get(),
            'payouts' => $payouts,
            'filters' => $filters,
        ]);
    }

    /** Той самий фільтр, що й на екрані. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filtersFrom($request);

        $payouts = $this->applyFilters(BonusPayout::query(), $filters)
            ->with('user:id,name')
            ->orderByDesc('week_start')
            ->get();

        $rows = $payouts->map(fn (BonusPayout $p) => [
            $p->user?->name ?? '—',
            // week_start — 'date' колонка, але НЕ закастована в моделі
            // (лишається сирим рядком з БД, як і в даних, що йдуть на
            // фронт) — ->format() на ній впав би фатальною помилкою.
            $p->week_start ? \Illuminate\Support\Carbon::parse($p->week_start)->format('d.m.Y') : '',
            $p->bizwar_amount,
            $p->contract_amount,
            $p->streak_bonus_amount,
            $p->contracts_count_bonus_amount,
            $p->investment_bonus_amount,
            $p->total_amount,
            $p->paid ? 'Так' : 'Ні',
            $p->paid_at?->format('d.m.Y H:i'),
        ]);

        return CsvExport::stream('bonuses.csv', [
            'Учасник', 'Тиждень від', 'Бізвар', 'Контракти', 'Бонус за серію',
            "Бонус за к-сть", 'Інвестиційний тір', 'Разом', 'Виплачено', 'Дата виплати',
        ], $rows);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bizwar_base_rate' => ['required', 'integer', 'min:0'],
            'contract_light_rate' => ['required', 'integer', 'min:0'],
            'contract_medium_rate' => ['required', 'integer', 'min:0'],
            'contract_heavy_rate' => ['required', 'integer', 'min:0'],
            'streak_threshold' => ['nullable', 'integer', 'min:1'],
            'streak_bonus_amount' => ['required', 'integer', 'min:0'],
            'contracts_count_threshold' => ['nullable', 'integer', 'min:1'],
            'contracts_count_bonus_amount' => ['required', 'integer', 'min:0'],
            'min_digest_amount' => ['required', 'integer', 'min:0'],
        ]);

        BonusSettings::current()->update($data);

        return back()->with('success', 'Налаштування премій збережено.');
    }

    public function storeTier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'threshold_amount' => ['required', 'integer', 'min:1'],
            'bonus_amount' => ['required', 'integer', 'min:0'],
        ]);

        InvestmentAchievementTier::create([
            ...$data,
            'sort_order' => (int) InvestmentAchievementTier::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Тір додано.');
    }

    public function destroyTier(InvestmentAchievementTier $tier): RedirectResponse
    {
        $tier->delete();

        return back()->with('success', 'Тір видалено.');
    }

    public function markPaid(BonusPayout $payout): JsonResponse
    {
        $payout->update([
            'paid' => true,
            'paid_at' => now(),
            'paid_by' => request()->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Позначено виплаченим.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    /**
     * Ручний запуск для тестування чи наздоганяння пропущеного тижня —
     * та сама логіка, що й у щотижневому scheduled-виклику.
     */
    public function runNow(): JsonResponse
    {
        $calculator = app(BonusCalculator::class);
        $weekStart = $calculator->currentWeekStart();
        $calculator->runWeeklyPayouts();
        app(BonusDigest::class)->sendFor($weekStart);

        return response()->json([
            'ok' => true,
            'message' => 'Розрахунок виконано.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
