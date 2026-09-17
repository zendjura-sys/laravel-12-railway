<?php

namespace Addons\Bonuses\Http\Controllers\Admin;

use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\BonusSettings;
use Addons\Bonuses\Models\InvestmentAchievementTier;
use Addons\Bonuses\Services\BonusCalculator;
use Addons\Bonuses\Services\BonusDigest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BonusAdminController
{
    public function index(): Response
    {
        $payouts = BonusPayout::query()
            ->with('user:id,name')
            ->orderByDesc('week_start')
            ->orderByDesc('total_amount')
            ->paginate(30);

        return Inertia::render('Admin/Bonuses/Index', [
            'settings' => BonusSettings::current(),
            'tiers' => InvestmentAchievementTier::query()->orderBy('sort_order')->orderBy('threshold_amount')->get(),
            'payouts' => $payouts,
        ]);
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
