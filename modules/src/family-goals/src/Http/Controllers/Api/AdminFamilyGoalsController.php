<?php

namespace Addons\FamilyGoals\Http\Controllers\Api;

use Addons\FamilyGoals\Http\Controllers\Admin\FamilyGoalsAdminController as WebFamilyGoalsAdminController;
use Addons\FamilyGoals\Models\FamilyGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Мобільний двійник Admin\FamilyGoalsAdminController — createGoal()
 * переиспользується, updateProgress()/close() з батьківського класу вже
 * повертають JSON і підключені напряму через окремі маршрути /api.
 */
class AdminFamilyGoalsController extends WebFamilyGoalsAdminController
{
    public function indexJson(): JsonResponse
    {
        $goals = FamilyGoal::query()
            ->with('creator:id,name')
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
                'creatorName' => $goal->creator?->name,
            ]);

        return response()->json(['goals' => $goals, 'metrics' => FamilyGoal::METRICS]);
    }

    public function storeJson(Request $request): JsonResponse
    {
        $goal = $this->createGoal($this->validated($request), $request->user());

        return response()->json([
            'ok' => true,
            'message' => 'Ціль створено.',
            'data' => ['id' => $goal->id],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
