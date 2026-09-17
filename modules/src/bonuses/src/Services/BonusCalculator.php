<?php

namespace Addons\Bonuses\Services;

use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\BonusSettings;
use Addons\Bonuses\Models\InvestmentAchievementTier;
use Addons\Bonuses\Models\UserInvestmentAchievement;
use Addons\Notifications\Services\NotificationService;
use Addons\Progression\Models\ProgressionProfile;
use Addons\Reports\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Carbon as SupportCarbon;

/**
 * Тижневий розрахунок грошових премій (₴). Усі ставки й пороги — з
 * BonusSettings (адмінка), тут жодного захардкодженого числа.
 *
 * Progression і Notifications читаються через class_exists() — той самий
 * підхід "опційна залежність", що й у Notifications/Reports: якщо модуль
 * не встановлено, відповідна складова просто не рахується, без помилки.
 * Reports — єдина ОБОВ'ЯЗКОВА залежність (без звітів рахувати нічого).
 */
class BonusCalculator
{
    public function runWeeklyPayouts(): void
    {
        $weekStart = $this->currentWeekStart();
        $weekEnd = $weekStart->copy()->addWeek();
        $settings = BonusSettings::current();

        $activeUserIds = Report::query()
            ->where('status', 'approved')
            ->whereIn('type', ['bizwar', 'contract'])
            ->whereBetween('reviewed_at', [$weekStart, $weekEnd])
            ->pluck('user_id');

        // Інвестиційний тір — одноразова премія за кумулятивну суму, не
        // прив'язана до активності САМЕ цього тижня, тому окремий скоуп
        // користувачів: будь-хто, у кого взагалі є затверджена інвестиція.
        $investmentUserIds = Report::query()
            ->where('status', 'approved')->where('type', 'investment')
            ->pluck('user_id');

        $userIds = $activeUserIds->merge($investmentUserIds)->unique();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            // Уже виплачений тиждень — недоторканний: повторний прогін (ручний
            // ретрай чи випадковий подвійний виклик) не повинен тихо міняти
            // суму, яку адмін уже позначив як фактично видану. Нові
            // інвестиційні тіри, якщо з'являться, підуть у розрахунок
            // наступного тижня.
            $existing = BonusPayout::query()
                ->where('user_id', $userId)
                ->where('week_start', $weekStart->toDateString())
                ->first();
            if ($existing?->paid) {
                continue;
            }

            $bizwar = $this->calculateBizwar($userId, $weekStart, $weekEnd, $settings);
            $contracts = $this->calculateContracts($userId, $weekStart, $weekEnd, $settings);
            $streakBonus = $this->calculateStreakBonus($userId, $settings);
            $contractsCountBonus = ($settings->contracts_count_threshold !== null
                && $contracts['count'] >= $settings->contracts_count_threshold)
                ? (int) $settings->contracts_count_bonus_amount
                : 0;
            $investmentBonus = $this->calculateInvestmentBonus($user);

            $total = $bizwar['amount'] + $contracts['amount'] + $streakBonus + $contractsCountBonus + $investmentBonus;

            BonusPayout::updateOrCreate(
                ['user_id' => $userId, 'week_start' => $weekStart->toDateString()],
                [
                    'bizwar_amount' => $bizwar['amount'],
                    'bizwar_winrate' => $bizwar['winrate'],
                    'contract_amount' => $contracts['amount'],
                    'contracts_count' => $contracts['count'],
                    'streak_bonus_amount' => $streakBonus,
                    'contracts_count_bonus_amount' => $contractsCountBonus,
                    'investment_bonus_amount' => $investmentBonus,
                    'total_amount' => $total,
                ],
            );
        }
    }

    /**
     * Субота 23:00 Europe/Kyiv — останній такий момент, що вже настав.
     * Той самий приём, що й у Progression::runWeeklyBonuses() для неділі.
     */
    public function currentWeekStart(): SupportCarbon
    {
        $now = now('Europe/Kyiv');
        $weekStart = $now->copy()->startOfWeek(Carbon::SATURDAY)->setTime(23, 0);
        if ($weekStart->gt($now)) {
            $weekStart->subWeek();
        }

        return $weekStart;
    }

    /** @return array{amount:int,winrate:?float} */
    private function calculateBizwar(int $userId, SupportCarbon $weekStart, SupportCarbon $weekEnd, BonusSettings $settings): array
    {
        $totals = Report::query()
            ->where('user_id', $userId)->where('status', 'approved')->where('type', 'bizwar')
            ->whereBetween('reviewed_at', [$weekStart, $weekEnd])
            ->selectRaw('COALESCE(SUM(wins_count),0) as wins, COALESCE(SUM(losses_count),0) as losses')
            ->first();

        $wins = (int) $totals->wins;
        $losses = (int) $totals->losses;
        $played = $wins + $losses;

        if ($played === 0) {
            return ['amount' => 0, 'winrate' => null];
        }

        $winrate = $wins / $played;

        return [
            'amount' => (int) round($settings->bizwar_base_rate * $winrate),
            'winrate' => round($winrate * 100, 2),
        ];
    }

    /** @return array{amount:int,count:int} */
    private function calculateContracts(int $userId, SupportCarbon $weekStart, SupportCarbon $weekEnd, BonusSettings $settings): array
    {
        $reports = Report::query()
            ->where('user_id', $userId)->where('status', 'approved')->where('type', 'contract')
            ->whereBetween('reviewed_at', [$weekStart, $weekEnd])
            ->get(['weight', 'light_count', 'medium_count', 'heavy_count']);

        $light = $medium = $heavy = 0;
        foreach ($reports as $report) {
            if ($report->weight !== null) {
                // Історичний формат: один звіт — один контракт однієї ваги.
                $light += $report->weight === 'light' ? 1 : 0;
                $medium += $report->weight === 'medium' ? 1 : 0;
                $heavy += $report->weight === 'heavy' ? 1 : 0;
            } else {
                $light += $report->light_count ?? 0;
                $medium += $report->medium_count ?? 0;
                $heavy += $report->heavy_count ?? 0;
            }
        }

        $amount = $light * $settings->contract_light_rate
            + $medium * $settings->contract_medium_rate
            + $heavy * $settings->contract_heavy_rate;

        return ['amount' => (int) $amount, 'count' => $light + $medium + $heavy];
    }

    private function calculateStreakBonus(int $userId, BonusSettings $settings): int
    {
        if ($settings->streak_threshold === null || ! class_exists(ProgressionProfile::class)) {
            return 0;
        }

        $profile = ProgressionProfile::query()->where('user_id', $userId)->first();
        if (! $profile || $profile->current_streak < $settings->streak_threshold) {
            return 0;
        }

        return (int) $settings->streak_bonus_amount;
    }

    private function calculateInvestmentBonus(User $user): int
    {
        $cumulative = (int) Report::query()
            ->where('user_id', $user->id)->where('status', 'approved')->where('type', 'investment')
            ->sum('amount');

        if ($cumulative <= 0) {
            return 0;
        }

        $earnedTierIds = UserInvestmentAchievement::query()->where('user_id', $user->id)->pluck('tier_id');

        $newlyEligible = InvestmentAchievementTier::query()
            ->where('threshold_amount', '<=', $cumulative)
            ->whereNotIn('id', $earnedTierIds)
            ->get();

        $bonus = 0;
        foreach ($newlyEligible as $tier) {
            $achievement = UserInvestmentAchievement::firstOrCreate(
                ['user_id' => $user->id, 'tier_id' => $tier->id],
                ['earned_at' => now()],
            );

            if ($achievement->wasRecentlyCreated) {
                $bonus += $tier->bonus_amount;
                $this->notifyInvestmentTier($user, $tier);
            }
        }

        return $bonus;
    }

    private function notifyInvestmentTier(User $user, InvestmentAchievementTier $tier): void
    {
        if (! class_exists(NotificationService::class)) {
            return;
        }

        app(NotificationService::class)->notify(
            $user,
            'investment_tier_unlocked',
            'Новий інвестиційний тір!',
            "Ви досягли рівня «{$tier->label}» — премія {$tier->bonus_amount}₴ увійде в найближчий тижневий розрахунок.",
        );
    }
}
