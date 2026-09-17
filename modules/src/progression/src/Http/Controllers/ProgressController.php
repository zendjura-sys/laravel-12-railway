<?php

namespace Addons\Progression\Http\Controllers;

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

    public function leaderboard(): Response
    {
        $top = ProgressionProfile::with('user:id,name,first_name,last_name,position_key')
            ->orderByDesc('xp')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->user?->name ?? '—',
                'xp' => $p->xp,
                'level' => $p->level(),
                'position' => $p->user?->position_title,
            ]);

        return Inertia::render('Progression/Leaderboard', ['leaderboard' => $top]);
    }
}
