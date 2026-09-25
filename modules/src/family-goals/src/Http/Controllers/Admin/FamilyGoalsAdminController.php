<?php

namespace Addons\FamilyGoals\Http\Controllers\Admin;

use Addons\FamilyGoals\Events\FamilyGoalCompleted;
use Addons\FamilyGoals\Models\ActivityEvent;
use Addons\FamilyGoals\Models\FamilyGoal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FamilyGoalsAdminController
{
    public function index(): Response
    {
        $goals = FamilyGoal::query()
            ->with('creator:id,name,gender')
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
                'creator' => $goal->creator,
            ]);

        return Inertia::render('Admin/FamilyGoals/Index', [
            'goals' => $goals,
            'metrics' => FamilyGoal::METRICS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->createGoal($this->validated($request), $request->user());

        return back()->with('success', 'Ціль створено.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_value' => ['nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metric' => ['nullable', 'string', Rule::in(array_keys(FamilyGoal::METRICS))],
            'deadline' => ['nullable', 'date'],
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function createGoal(array $data, User $user): FamilyGoal
    {
        $goal = FamilyGoal::create([
            ...$data,
            'created_by' => $user->id,
        ]);

        ActivityEvent::log('goal_created', $user->id, "Нова ціль родини: «{$goal->title}»");

        return $goal;
    }

    public function updateProgress(Request $request, FamilyGoal $familyGoal): JsonResponse
    {
        $data = $request->validate([
            'current_value' => ['required', 'integer', 'min:0'],
        ]);

        $familyGoal->current_value = $data['current_value'];

        $justCompleted = $familyGoal->status === 'active'
            && $familyGoal->target_value
            && $familyGoal->current_value >= $familyGoal->target_value;

        if ($justCompleted) {
            $familyGoal->status = 'completed';
        }

        $familyGoal->save();

        if ($justCompleted) {
            ActivityEvent::log('goal_completed', $request->user()->id, "Ціль «{$familyGoal->title}» досягнута! 🎉");
            Event::dispatch(new FamilyGoalCompleted($familyGoal));
        } else {
            ActivityEvent::log('goal_progress', $request->user()->id, "Прогрес цілі «{$familyGoal->title}»: {$familyGoal->current_value}" . ($familyGoal->unit ? " {$familyGoal->unit}" : ''));
        }

        return response()->json([
            'ok' => true,
            'message' => $justCompleted ? 'Ціль досягнута!' : 'Прогрес оновлено.',
            'data' => ['goal' => [
                'current_value' => $familyGoal->current_value,
                'status' => $familyGoal->status,
                'progress_percent' => $familyGoal->progressPercent(),
            ]],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function close(Request $request, FamilyGoal $familyGoal): JsonResponse
    {
        if ($familyGoal->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'Ця ціль вже не активна.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $familyGoal->update(['status' => 'failed']);
        ActivityEvent::log('goal_closed', $request->user()->id, "Ціль «{$familyGoal->title}» закрита без досягнення.");

        return response()->json([
            'ok' => true,
            'message' => 'Ціль закрита.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
