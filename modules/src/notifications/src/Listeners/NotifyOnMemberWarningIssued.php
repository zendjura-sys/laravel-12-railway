<?php

namespace Addons\Notifications\Listeners;

use Addons\MemberCenter\Models\MemberWarning;
use Addons\Notifications\Services\NotificationService;
use App\Models\User;

/**
 * На відміну від приватних HR-нотаток, попередження — офіційна дія:
 * учасник одразу дізнається про нього (web + Telegram, якщо привʼязано),
 * той самий приём, що й для рішення по відпустці.
 */
class NotifyOnMemberWarningIssued
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $warning = $event->warning;
        $user = User::find($warning->user_id);
        if (! $user) {
            return;
        }

        $label = MemberWarning::SEVERITY_LABELS[$warning->severity] ?? 'Попередження';

        $this->notifications->notify(
            $user,
            'member_warning_issued',
            $label,
            $warning->reason,
        );
    }
}
