<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Models\Notification;

/**
 * Слухає report.reviewed від Reports (якщо він встановлений і активний —
 * див. коментар у routes/events.php). Просто створює особисте сповіщення
 * автору звіту, нічого не блокує в основному потоці Reports.
 */
class NotifyOnReportReviewed
{
    public function handle($event): void
    {
        $report = $event->report;
        $verb = $report->status === 'approved' ? 'затверджено' : 'відхилено';

        Notification::notify(
            $report->user_id,
            'report_reviewed',
            'Ваш звіт розглянуто',
            "Звіт ({$report->type}) {$verb}." . ($report->review_note ? " Коментар: {$report->review_note}" : ''),
        );
    }
}
