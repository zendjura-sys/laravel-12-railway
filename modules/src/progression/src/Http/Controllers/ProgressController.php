<?php

namespace Addons\Progression\Http\Controllers;

use Addons\Bonuses\Models\BonusPayout;
use Addons\Progression\Models\Achievement;
use Addons\Progression\Models\ProgressionProfile;
use Addons\Progression\Models\UserAchievement;
use Addons\Progression\Models\XpLedgerEntry;
use Addons\Progression\Services\ProgressionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController
{
    public function __construct(private readonly ProgressionService $service)
    {
    }

    /** /progress — /stats и /progress из брифа объединены в одну карточку. */
    public function index(Request $request): Response
    {
        $profile = $this->service->profileFor($request->user());

        $earnedIds = UserAchievement::where('user_id', $request->user()->id)->pluck('achievement_id');

        $achievements = Achievement::all()->map(fn ($a) => [
            'code' => $a->code,
            'name' => $a->name,
            'description' => $a->description,
            'earned' => $earnedIds->contains($a->id),
        ]);

        $battleLog = XpLedgerEntry::where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(30)
            ->get();

        return Inertia::render('Progression/Index', [
            'profile' => [
                'xp' => $profile->xp,
                'xpTrend' => $this->xpTrend($battleLog, $profile->xp),
                'level' => $profile->level(),
                // Посада родини — окрема річ від XP-рівня тут: перша
                // призначається керівництвом за якісними критеріями,
                // другий — просто лічильник активності.
                'position' => $request->user()->position_title,
                'kapt_wins' => $profile->kapt_wins,
                'kapt_losses' => $profile->kapt_losses,
                'contracts_count' => $profile->contracts_count,
                'current_streak' => $profile->current_streak,
                'longest_streak' => $profile->longest_streak,
            ],
            'achievements' => $achievements,
            'battleLog' => $battleLog,
        ]);
    }

    /**
     * Спарклайн на «Мій прогрес»: як змінювався XP за останні (до 30)
     * нарахувань. battleLog приходить новими-спочатку, а для графіка
     * потрібен хронологічний порядок — і не самі суми нарахувань, а
     * НАКОПИЧЕНЕ значення XP в кожній точці. Рахуємо назад від поточного
     * profile.xp (єдине надійне джерело правди — не довіряємо, що сума
     * всіх ledger-записів колись рахувалась без розбіжностей).
     *
     * @param  \Illuminate\Support\Collection<int, XpLedgerEntry>  $battleLog
     * @return array<int, array{t: string, xp: int}>
     */
    private function xpTrend($battleLog, int $currentXp): array
    {
        $asc = $battleLog->sortBy('created_at')->values();
        $running = $currentXp - $asc->sum('amount');

        return $asc->map(function ($entry) use (&$running) {
            $running += $entry->amount;

            return ['t' => $entry->created_at->toISOString(), 'xp' => $running];
        })->values()->all();
    }

    /**
     * Категорії рейтингу — не лише XP: одна цифра нічого не каже про те,
     * хто справді тягне бізвари, а хто контракти. «Премії» показуємо
     * тільки якщо встановлено Bonuses (class_exists) — та сама опційна
     * залежність, що вже читає бота в BotHandler::statsScreen().
     *
     * @return array<string,string>
     */
    private function categories(): array
    {
        $categories = [
            'xp' => 'Активність',
            'bizwar' => 'Бізвар',
            'contracts' => 'Контракти',
            'streak' => 'Серія перемог',
        ];

        if (class_exists(BonusPayout::class)) {
            $categories['bonuses'] = 'Премії';
        }

        return $categories;
    }

    public function leaderboard(Request $request): Response
    {
        $categories = $this->categories();
        $category = $request->query('category', 'xp');
        if (! array_key_exists($category, $categories)) {
            $category = 'xp';
        }

        $top = $category === 'bonuses'
            ? $this->bonusesLeaderboard()
            : $this->profileLeaderboard($category);

        return Inertia::render('Progression/Leaderboard', [
            'leaderboard' => $top,
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function profileLeaderboard(string $category): array
    {
        $query = ProgressionProfile::with('user:id,name,first_name,last_name,position_key');

        $query = match ($category) {
            'bizwar' => $query->orderByDesc('kapt_wins')->orderBy('kapt_losses'),
            'contracts' => $query->orderByDesc('contracts_count')->orderByDesc('heavy_contracts_count'),
            'streak' => $query->orderByDesc('current_streak')->orderByDesc('longest_streak'),
            default => $query->orderByDesc('xp'),
        };

        return $query->limit(50)->get()->map(fn (ProgressionProfile $p) => [
            'name' => $p->user?->name ?? '—',
            'position' => $p->user?->position_title,
            'xp' => $p->xp,
            'level' => $p->level(),
            'kapt_wins' => $p->kapt_wins,
            'kapt_losses' => $p->kapt_losses,
            'contracts_count' => $p->contracts_count,
            'heavy_contracts_count' => $p->heavy_contracts_count,
            'current_streak' => $p->current_streak,
            'longest_streak' => $p->longest_streak,
        ])->values()->all();
    }

    /** Сума всіх тижневих виплат за весь час — не лише поточний тиждень. */
    private function bonusesLeaderboard(): array
    {
        return BonusPayout::query()
            ->select('user_id')
            ->selectRaw('SUM(total_amount) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(50)
            ->with('user:id,name,first_name,last_name,position_key')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->user?->name ?? '—',
                'position' => $row->user?->position_title,
                'total_amount' => (int) $row->total,
            ])
            ->values()
            ->all();
    }
}
