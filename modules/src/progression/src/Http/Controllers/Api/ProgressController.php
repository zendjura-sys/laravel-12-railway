<?php

namespace Addons\Progression\Http\Controllers\Api;

use Addons\Progression\Http\Controllers\ProgressController as WebProgressController;
use Addons\Progression\Models\Achievement;
use Addons\Progression\Models\UserAchievement;
use Addons\Progression\Models\XpLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Мобільний двійник Http\Controllers\ProgressController — успадковує
 * приватну-стала-protected бізнес-логіку (categories()/profileLeaderboard()/
 * bonusesLeaderboard()/balanceLeaderboard()/xpTrend()) з веб-контролера
 * замість дублювання, і лише переозначає index()/leaderboard() під JSON.
 */
class ProgressController extends WebProgressController
{
    // Не index()/leaderboard() — інакше PHP вимагає коваріантний тип
    // повернення з батьківського Inertia\Response, а тут JsonResponse
    // (несумісні типи, fatal error при завантаженні класу).
    public function indexJson(Request $request): JsonResponse
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

        return response()->json([
            'profile' => [
                'xp' => $profile->xp,
                'xpTrend' => $this->xpTrend($battleLog, $profile->xp),
                'level' => $profile->level(),
                'position' => $request->user()->position_title,
                'kapt_wins' => $profile->kapt_wins,
                'kapt_losses' => $profile->kapt_losses,
                'contracts_count' => $profile->contracts_count,
                'current_streak' => $profile->current_streak,
                'longest_streak' => $profile->longest_streak,
            ],
            'achievements' => $achievements,
        ]);
    }

    public function leaderboardJson(Request $request): JsonResponse
    {
        $categories = $this->categories();
        $category = $request->query('category', 'xp');
        if (! array_key_exists($category, $categories)) {
            $category = 'xp';
        }

        $top = match ($category) {
            'bonuses' => $this->bonusesLeaderboard(),
            'balance' => $this->balanceLeaderboard(),
            default => $this->profileLeaderboard($category),
        };

        return response()->json([
            'leaderboard' => $top,
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    public function hallOfFameJson(): JsonResponse
    {
        return response()->json(['records' => $this->hallOfFameRecords()]);
    }
}
