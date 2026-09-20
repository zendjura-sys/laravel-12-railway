<?php

namespace Addons\Bonuses\Http\Controllers;

use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\InvestmentAchievementTier;
use Addons\Bonuses\Models\ManualBonusAward;
use Addons\Bonuses\Models\UserInvestmentAchievement;
use Addons\Reports\Models\Report;
use App\Support\MemberCard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BonusController
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $payouts = BonusPayout::query()
            ->where('user_id', $userId)
            ->orderByDesc('week_start')
            ->paginate(15);

        $cumulativeInvestment = (int) Report::query()
            ->where('user_id', $userId)->where('status', 'approved')->where('type', 'investment')
            ->sum('amount');

        $earnedTierIds = UserInvestmentAchievement::query()->where('user_id', $userId)->pluck('tier_id');

        $tiers = InvestmentAchievementTier::query()
            ->orderBy('sort_order')->orderBy('threshold_amount')
            ->get()
            ->map(fn (InvestmentAchievementTier $tier) => [
                'id' => $tier->id,
                'label' => $tier->label,
                'threshold_amount' => $tier->threshold_amount,
                'bonus_amount' => $tier->bonus_amount,
                'earned' => $earnedTierIds->contains($tier->id),
            ]);

        $manualAwards = ManualBonusAward::query()
            ->where('user_id', $userId)
            ->latest()
            ->get();

        // Разом — авто-нарахування (усі тижні, незалежно від "виплачено")
        // + ручні премії; те, що показує картка вгорі сторінки.
        $totalEarned = (int) BonusPayout::query()->where('user_id', $userId)->sum('total_amount')
            + (int) $manualAwards->sum('amount');

        return Inertia::render('Bonuses/Index', [
            'payouts' => $payouts,
            'cumulativeInvestment' => $cumulativeInvestment,
            'tiers' => $tiers,
            'manualAwards' => $manualAwards,
            'card' => [
                'number' => MemberCard::masked($request->user()),
                'name' => $request->user()->name,
                'totalEarned' => $totalEarned,
            ],
        ]);
    }
}
