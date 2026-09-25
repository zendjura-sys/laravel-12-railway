<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Services\NotificationService;
use App\Models\User;

class NotifyOnLeaveRequestReviewed
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $leaveRequest = $event->leaveRequest;
        $user = User::find($leaveRequest->user_id);
        if (! $user) {
            return;
        }

        $verb = $leaveRequest->status === 'approved' ? 'затверджено' : 'відхилено';
        $period = $leaveRequest->starts_on->format('d.m.Y').' — '.$leaveRequest->ends_on->format('d.m.Y');

        $this->notifications->notify(
            $user,
            'leave_request_reviewed',
            'Заявку на відпустку розглянуто',
            "Заявку на {$period} {$verb}.".($leaveRequest->review_note ? " Коментар: {$leaveRequest->review_note}" : ''),
        );
    }
}
