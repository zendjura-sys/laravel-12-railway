<?php

namespace Addons\MemberCenter\Http\Controllers;

use Addons\MemberCenter\Events\LeaveRequestCreated;
use Addons\MemberCenter\Models\LeaveRequest;
use Addons\MemberCenter\Models\MemberProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Публічна (для самого учасника) сторона модуля: власний статус і власні
 * заявки на відпустку. Приватні HR-нотатки сюди не потрапляють — вони
 * лише в Admin\MemberController.
 */
class MemberCenterController
{
    public function index(Request $request): Response
    {
        $profile = MemberProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $leaveRequests = LeaveRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest('starts_on')
            ->get();

        return Inertia::render('MemberCenter/Index', [
            'hrStatus' => $profile->hr_status,
            'leaveRequests' => $leaveRequests,
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $leaveRequest = LeaveRequest::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        // Раніше нова заявка нікого не сповіщала — той, у кого є право
        // members.manage, дізнавався про неї, лише сам зайшовши в
        // адмінку. Той самий патерн, що й ReportCreated у Reports.
        Event::dispatch(new LeaveRequestCreated($leaveRequest));

        return back()->with('success', 'Заявку подано, очікує на розгляд.');
    }
}
