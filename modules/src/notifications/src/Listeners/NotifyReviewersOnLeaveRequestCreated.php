<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Нова заявка на відпустку лежить непоміченою, поки хтось із правом
 * members.manage сам не зайде в адмінку — той самий патерн, що й
 * NotifyReviewersOnReportCreated для звітів.
 */
class NotifyReviewersOnLeaveRequestCreated
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $leaveRequest = $event->leaveRequest;
        $submitter = User::find($leaveRequest->user_id);

        $period = $leaveRequest->starts_on->format('d.m.Y').' — '.$leaveRequest->ends_on->format('d.m.Y');
        $title = 'Нова заявка на відпустку';
        $body = e($submitter?->name ?? 'Учасник').' '.($submitter?->verb('подав', 'подала') ?? 'подав')
            ." заявку на {$period} — потребує розгляду.";

        $button = Route::has('admin.members.index')
            ? ['text' => '🏖  Заявки на розгляді', 'url' => route('admin.members.index')]
            : null;

        User::permission('members.manage')->get()->each(
            fn (User $reviewer) => $this->notifications->notify($reviewer, 'leave_request_created', $title, $body, $button)
        );
    }
}
