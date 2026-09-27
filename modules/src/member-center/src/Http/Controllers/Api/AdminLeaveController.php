<?php

namespace Addons\MemberCenter\Http\Controllers\Api;

use Addons\MemberCenter\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Мінімальна адмінка в застосунку: лише заявки на відпустку, що чекають
 * рішення. approveLeave()/rejectLeave() не дублюються — маршрути /api
 * вказують напряму на Admin\MemberController, ті методи вже JSON.
 */
class AdminLeaveController
{
    public function pendingJson(Request $request): JsonResponse
    {
        $requests = LeaveRequest::query()
            ->with('user:id,name')
            ->where('status', 'pending')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'userName' => $r->user?->name,
                'startsOn' => $r->starts_on?->toDateString(),
                'endsOn' => $r->ends_on?->toDateString(),
                'reason' => $r->reason,
                'createdAt' => $r->created_at,
            ]);

        return response()->json(['leaveRequests' => $requests]);
    }
}
