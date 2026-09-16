<?php

namespace Addons\Progression\Services;

use Addons\Progression\Models\Achievement;
use Addons\Progression\Models\ProgressionProfile;
use Addons\Progression\Models\UserAchievement;
use Addons\Progression\Models\XpLedgerEntry;
use Addons\Reports\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Единая точка входа для всего, что связано с прогрессом участника.
 * Публичный awardXp() — тот самый метод, через который Member Center (в
 * будущем) будет делать ручные корректировки статистики, как описано в
 * брифе для раздела Member Center & HR.
 */
class ProgressionService
{
    // XP за события — фиксировано брифом.
    private const XP_KAPT_WIN = 120;
    private const XP_KAPT_LOSS = 40;
    private const XP_CONTRACT = ['light' => 30, 'medium' => 45, 'heavy' => 60];

    // Бонусы еженедельных челленджей.
    private const WEEKLY_KAPT_WINS_THRESHOLD = 5;
    private const WEEKLY_KAPT_WINS_BONUS = 300;
    private const WEEKLY_CONTRACTS_THRESHOLD = 10;
    private const WEEKLY_CONTRACTS_BONUS = 250;

    // Sharpshooter: критерий не задан брифом явно — фиксирую как константу,
    // чтобы можно было поменять одной строкой без переписывания логики.
    private const SHARPSHOOTER_HEAVY_CONTRACTS = 10;

    public function profileFor(User $user): ProgressionProfile
    {
        return ProgressionProfile::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Общий метод начисления XP — и для событий отчётов, и для ручных
     * корректировок администратором/офицером.
     */
    public function awardXp(
        User $user,
        int $amount,
        string $reason,
        string $sourceType,
        ?int $sourceId = null,
        ?User $awardedBy = null,
    ): void {
        DB::transaction(function () use ($user, $amount, $reason, $sourceType, $sourceId, $awardedBy) {
            $profile = $this->profileFor($user);

            XpLedgerEntry::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'awarded_by' => $awardedBy?->id,
            ]);

            $profile->increment('xp', $amount);
            $profile->update(['last_activity_at' => now()]);
        });
    }

    /**
     * Вызывается слушателем report.reviewed ТОЛЬКО для status=approved.
     * type=other не начисляет XP — брифом не определено.
     */
    public function handleApprovedReport(Report $report): void
    {
        $user = $report->user;
        $profile = $this->profileFor($user);

        if ($report->type === 'kapt') {
            $isWin = $report->outcome === 'win';
            $amount = $isWin ? self::XP_KAPT_WIN : self::XP_KAPT_LOSS;

            $wasFirstWin = $isWin && $profile->kapt_wins === 0;

            if ($isWin) {
                $profile->increment('kapt_wins');
                $profile->increment('current_streak');
                if ($profile->current_streak > $profile->longest_streak) {
                    $profile->update(['longest_streak' => $profile->current_streak]);
                }
            } else {
                $profile->increment('kapt_losses');
                $profile->update(['current_streak' => 0]);
            }

            $this->awardXp($user, $amount, "KAPT {$report->outcome}", 'report', $report->id);

            if ($wasFirstWin) {
                $this->unlock($user, 'first_blood');
            }
            foreach ([5, 15, 30] as $threshold) {
                if ($profile->fresh()->current_streak === $threshold) {
                    $this->unlock($user, "streak_{$threshold}");
                }
            }
        } elseif ($report->type === 'contract') {
            $amount = self::XP_CONTRACT[$report->weight] ?? 0;

            $profile->increment('contracts_count');
            if ($report->weight === 'heavy') {
                $profile->increment('heavy_contracts_count');
            }

            $this->awardXp($user, $amount, "Контракт ({$report->weight})", 'report', $report->id);

            $freshProfile = $profile->fresh();
            foreach ([25, 100, 250] as $threshold) {
                if ($freshProfile->contracts_count === $threshold) {
                    $this->unlock($user, "contracts_{$threshold}");
                }
            }
            if ($freshProfile->heavy_contracts_count === self::SHARPSHOOTER_HEAVY_CONTRACTS) {
                $this->unlock($user, 'sharpshooter');
            }
        }
        // type=other: без начисления XP, только сам факт репорта остаётся в reports.
    }

    private function unlock(User $user, string $achievementCode): void
    {
        $achievement = Achievement::where('code', $achievementCode)->first();
        if (! $achievement) {
            return;
        }

        UserAchievement::firstOrCreate(
            ['user_id' => $user->id, 'achievement_id' => $achievement->id],
            ['earned_at' => now()],
        );
    }

    /**
     * Тижневий бонус — рахує ЗАТВЕРДЖЕНІ звіти в межах поточного тижня
     * (Sunday 20:00 Europe/Kyiv за брифом) і нараховує XP, якщо пороги
     * досягнуто. Викликається scheduled-таском щотижня.
     */
    public function runWeeklyBonuses(): void
    {
        // "Последнее воскресенье 20:00 Europe/Kyiv, которое уже наступило" —
        // startOfWeek(SUNDAY) даёт воскресенье ТЕКУЩЕЙ недели (может быть
        // сегодня), а не "предыдущее" в смысле Carbon::previous() (который
        // всегда откатывает минимум на неделю назад, даже если сегодня уже
        // воскресенье после 20:00 — это давало неверную границу).
        $now = now('Europe/Kyiv');
        $weekStart = $now->copy()->startOfWeek(\Carbon\Carbon::SUNDAY)->setTime(20, 0);
        if ($weekStart->gt($now)) {
            $weekStart->subWeek();
        }

        $userIds = Report::query()
            ->where('status', 'approved')
            ->where('reviewed_at', '>=', $weekStart)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            $kaptWins = Report::query()
                ->where('user_id', $userId)->where('status', 'approved')
                ->where('type', 'kapt')->where('outcome', 'win')
                ->where('reviewed_at', '>=', $weekStart)->count();

            $contracts = Report::query()
                ->where('user_id', $userId)->where('status', 'approved')
                ->where('type', 'contract')
                ->where('reviewed_at', '>=', $weekStart)->count();

            if ($kaptWins >= self::WEEKLY_KAPT_WINS_THRESHOLD) {
                $this->awardXp($user, self::WEEKLY_KAPT_WINS_BONUS, 'Тижневий бонус: 5+ перемог KAPT', 'weekly_bonus');
            }
            if ($contracts >= self::WEEKLY_CONTRACTS_THRESHOLD) {
                $this->awardXp($user, self::WEEKLY_CONTRACTS_BONUS, 'Тижневий бонус: 10+ контрактів', 'weekly_bonus');
            }
        }
    }
}
