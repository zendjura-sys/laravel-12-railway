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
            $investmentBonus = $this->calculateInvestmentBonus($user, $weekStart, $weekEnd);

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

    /**
     * Рахує КОЖЕН звіт (день) окремо — власний winrate дня × ставка ×
     * множник оцінки цього конкретного звіту — і сумує за тиждень. Раніше
     * тут був один агрегатний winrate на весь тиждень, але оцінка
     * ставиться на кожен звіт при затвердженні, а не на тиждень в цілому,
     * тож без посуточного рахунку не було б куди її прикласти.
     *
     * @return array{amount:int,winrate:?float}
     */
    private function calculateBizwar(int $userId, SupportCarbon $weekStart, SupportCarbon $weekEnd, BonusSettings $settings): array
    {
        $reports = Report::query()
            ->where('user_id', $userId)->where('status', 'approved')->where('type', 'bizwar')
            ->whereBetween('reviewed_at', [$weekStart, $weekEnd])
            ->get(['wins_count', 'losses_count', 'grade']);

        $amount = 0;
        $totalWins = 0;
        $totalPlayed = 0;

        foreach ($reports as $report) {
            $wins = (int) ($report->wins_count ?? 0);
            $losses = (int) ($report->losses_count ?? 0);
            $played = $wins + $losses;
            if ($played === 0) {
                continue;
            }

            $dayWinrate = $wins / $played;
            $amount += (int) round($settings->bizwar_base_rate * $dayWinrate * $report->gradeMultiplier());

            $totalWins += $wins;
            $totalPlayed += $played;
        }

        return [
            'amount' => $amount,
            // Сумарний winrate тижня лишається тільки для відображення в
            // адмінці/особистому кабінеті — на суму він більше не впливає.
            'winrate' => $totalPlayed > 0 ? round($totalWins / $totalPlayed * 100, 2) : null,
        ];
    }

    /**
     * Так само посуточно: сума ставок ЦЬОГО звіту × множник ЙОГО оцінки.
     *
     * @return array{amount:int,count:int}
     */
    private function calculateContracts(int $userId, SupportCarbon $weekStart, SupportCarbon $weekEnd, BonusSettings $settings): array
    {
        $reports = Report::query()
            ->where('user_id', $userId)->where('status', 'approved')->where('type', 'contract')
            ->whereBetween('reviewed_at', [$weekStart, $weekEnd])
            ->get(['weight', 'light_count', 'medium_count', 'heavy_count', 'grade']);

        $amount = 0;
        $totalCount = 0;

        foreach ($reports as $report) {
            if ($report->weight !== null) {
                // Історичний формат: один звіт — один контракт однієї ваги.
                $light = $report->weight === 'light' ? 1 : 0;
                $medium = $report->weight === 'medium' ? 1 : 0;
                $heavy = $report->weight === 'heavy' ? 1 : 0;
            } else {
                $light = $report->light_count ?? 0;
                $medium = $report->medium_count ?? 0;
                $heavy = $report->heavy_count ?? 0;
            }

            $dayAmount = $light * $settings->contract_light_rate
                + $medium * $settings->contract_medium_rate
                + $heavy * $settings->contract_heavy_rate;

            $amount += (int) round($dayAmount * $report->gradeMultiplier());
            $totalCount += $light + $medium + $heavy;
        }

        return ['amount' => $amount, 'count' => $totalCount];
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

    /**
     * Тір — одноразова премія за кумулятивну суму, не за конкретний
     * звіт, але оцінка все одно ставиться на звіт. Тому: тіри, вже
     * перетнуті сумою ДО цього тижня, зараховуються нейтрально (1.0,
     * жодного звіту цього тижня їх не спричинив); тіри, перетнуті ВЖЕ В
     * ЦЬОМУ тижні — множник оцінки того конкретного звіту, який довів
     * кумулятивну суму до порогу.
     */
    private function calculateInvestmentBonus(User $user, SupportCarbon $weekStart, SupportCarbon $weekEnd): int
    {
        $baseline = (int) Report::query()
            ->where('user_id', $user->id)->where('status', 'approved')->where('type', 'investment')
            ->where('reviewed_at', '<', $weekStart)
            ->sum('amount');

        $thisWeekReports = Report::query()
            ->where('user_id', $user->id)->where('status', 'approved')->where('type', 'investment')
            ->whereBetween('reviewed_at', [$weekStart, $weekEnd])
            ->orderBy('reviewed_at')
            ->get(['amount', 'grade']);

        $earnedTierIds = UserInvestmentAchievement::query()->where('user_id', $user->id)->pluck('tier_id');
        $tiers = InvestmentAchievementTier::query()->whereNotIn('id', $earnedTierIds)->orderBy('threshold_amount')->get();

        if ($tiers->isEmpty()) {
            return 0;
        }

        $bonus = 0;
        $running = $baseline;

        foreach ($tiers as $tier) {
            if ($running >= $tier->threshold_amount) {
                $bonus += $this->awardTierIfNew($user, $tier, 1.0);
            }
        }

        foreach ($thisWeekReports as $report) {
            $running += (int) $report->amount;
            foreach ($tiers as $tier) {
                if ($running >= $tier->threshold_amount) {
                    $bonus += $this->awardTierIfNew($user, $tier, $report->gradeMultiplier());
                }
            }
        }

        return $bonus;
    }

    private function awardTierIfNew(User $user, InvestmentAchievementTier $tier, float $multiplier): int
    {
        $achievement = UserInvestmentAchievement::firstOrCreate(
            ['user_id' => $user->id, 'tier_id' => $tier->id],
            ['earned_at' => now()],
        );

        if (! $achievement->wasRecentlyCreated) {
            return 0;
        }

        $this->notifyInvestmentTier($user, $tier);

        return (int) round($tier->bonus_amount * $multiplier);
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
