<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Services\NotificationService;
use App\Models\User;

/**
 * Слухає report.reviewed від Reports (якщо він встановлений і активний —
 * див. коментар у routes/events.php). Створює особисте сповіщення автору
 * звіту (web + Telegram, якщо прив'язано), нічого не блокує в основному
 * потоці Reports.
 */
class NotifyOnReportReviewed
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $report = $event->report;
        $user = User::find($report->user_id);
        if (! $user) {
            return;
        }

        $verb = $report->status === 'approved' ? 'затверджено' : 'відхилено';

        $this->notifications->notify(
            $user,
            'report_reviewed',
            'Ваш звіт розглянуто',
            "Звіт ({$report->type}) {$verb}." . ($report->review_note ? " Коментар: {$report->review_note}" : ''),
        );
    }
}
