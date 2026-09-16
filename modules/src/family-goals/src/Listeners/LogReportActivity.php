<?php

namespace Addons\FamilyGoals\Listeners;

use Addons\FamilyGoals\Models\ActivityEvent;

/**
 * Слухає report.created/report.reviewed від Reports (якщо він встановлений
 * і активний — routes/events.php підключається лише тоді, коли клас події
 * реально існує, див. коментар там). Просто пише подію у спільну стрічку,
 * нічого не рахує і не блокує основний потік Reports.
 */
class LogReportActivity
{
    public function handleCreated($event): void
    {
        $report = $event->report;
        ActivityEvent::log('report_created', $report->user_id, "{$report->user?->name} подав звіт ({$report->type})");
    }

    public function handleReviewed($event): void
    {
        $report = $event->report;
        $verb = $report->status === 'approved' ? 'затверджено' : 'відхилено';
        ActivityEvent::log('report_reviewed', $report->user_id, "Звіт {$report->user?->name} {$verb}");
    }
}
