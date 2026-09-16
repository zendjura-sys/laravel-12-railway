<?php

namespace Addons\MemberCenter\Http\Controllers\Admin;

use Addons\MemberCenter\Models\LeaveRequest;
use Addons\MemberCenter\Models\MemberNote;
use Addons\MemberCenter\Models\MemberProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController
{
    public function index(Request $request): Response
    {
        // Свідомо не додаємо hasMany/belongsTo для member_profiles/member_notes
        // до базової моделі App\Models\User — модуль лишається повністю
        // самодостатнім (не вимагає commit'у в базовий застосунок для звʼязків),
        // тому тут підзапити замість $user->memberProfile.
        $search = $request->string('q')->toString();

        $members = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->selectSub(
                MemberProfile::query()->select('hr_status')->whereColumn('user_id', 'users.id'),
                'hr_status',
            )
            ->selectSub(
                MemberNote::query()->selectRaw('count(*)')->whereColumn('user_id', 'users.id'),
                'notes_count',
            )
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hr_status' => $user->hr_status ?? 'active',
                'notes_count' => (int) $user->notes_count,
            ]);

        $pendingLeaveRequests = LeaveRequest::query()
            ->with('user:id,name')
            ->where('status', 'pending')
            ->orderBy('starts_on')
            ->get();

        return Inertia::render('Admin/Members/Index', [
            'members' => $members,
            'search' => $search,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'statuses' => MemberProfile::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'hr_status' => ['required', Rule::in(MemberProfile::STATUSES)],
        ]);

        MemberProfile::updateOrCreate(['user_id' => $user->id], $data);

        return response()->json([
            'ok' => true,
            'message' => 'Статус оновлено.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function notes(User $user): JsonResponse
    {
        $notes = MemberNote::query()
            ->with('author:id,name')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['notes' => $notes],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function storeNote(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $note = MemberNote::create([
            ...$data,
            'user_id' => $user->id,
            'author_id' => $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Нотатку додано.',
            'data' => ['note' => $note->load('author:id,name')],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->reviewLeave($request, $leaveRequest, 'approved');
    }

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->reviewLeave($request, $leaveRequest, 'rejected');
    }

    private function reviewLeave(Request $request, LeaveRequest $leaveRequest, string $status): JsonResponse
    {
        if (! $leaveRequest->isPending()) {
            return response()->json([
                'ok' => false,
                'message' => 'Заявку вже розглянуто раніше.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $leaveRequest->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => $status === 'approved' ? 'Заявку затверджено.' : 'Заявку відхилено.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
