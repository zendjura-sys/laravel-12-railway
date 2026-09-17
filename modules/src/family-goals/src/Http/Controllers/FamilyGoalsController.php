<?php

namespace Addons\FamilyGoals\Http\Controllers;

use Addons\FamilyGoals\Models\ActivityEvent;
use Addons\FamilyGoals\Models\FamilyGoal;
use Inertia\Inertia;
use Inertia\Response;

class FamilyGoalsController
{
    public function index(): Response
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
                'target_value' => $goal->target_value,
                'current_value' => $goal->current_value,
                'unit' => $goal->unit,
                'metric' => $goal->metric,
                'deadline' => $goal->deadline,
                'status' => $goal->status,
                'progress_percent' => $goal->progressPercent(),
            ]);

        $activity = ActivityEvent::query()
            ->with('user:id,name')
            ->latest()
            ->limit(30)
            ->get();

        return Inertia::render('FamilyGoals/Index', [
            'goals' => $goals,
            'activity' => $activity,
            'metrics' => FamilyGoal::METRICS,
        ]);
    }
}
