<?php

namespace Addons\FamilyGoals\Http\Controllers\Api;

use Addons\FamilyGoals\Models\ActivityEvent;
use Addons\FamilyGoals\Models\FamilyGoal;
use Illuminate\Http\JsonResponse;

/**
 * Мобільний двійник FamilyGoalsController — той самий список цілей і
 * стрічка активності, лише JSON замість Inertia::render().
 */
class FamilyGoalsController
{
    public function indexJson(): JsonResponse
    {
        $goals = FamilyGoal::query()
            ->whereIn('status', ['active', 'completed'])
            ->orderByRaw("status = 'active' desc")
            ->latest()
            ->get()
            ->map(fn (FamilyGoal $goal) => [
                'id' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'targetValue' => $goal->target_value,
                'currentValue' => $goal->current_value,
                'unit' => $goal->unit,
                'metric' => $goal->metric,
                'deadline' => $goal->deadline,
                'status' => $goal->status,
                'progressPercent' => $goal->progressPercent(),
            ]);

        $activity = ActivityEvent::query()
            ->with('user:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ActivityEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'message' => $event->message,
                'userName' => $event->user?->name,
                'createdAt' => $event->created_at,
            ]);

        return response()->json([
            'goals' => $goals,
            'activity' => $activity,
            'metrics' => FamilyGoal::METRICS,
        ]);
    }
}
