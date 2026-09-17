<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Новий звіт лежить непоміченим, поки хтось із правом reports.manage сам
 * не зайде в адмінку — тут сповіщаємо їх одразу: web-копія + Telegram (якщо
 * привʼязано), з кнопкою прямо на список звітів на розгляді.
 */
class NotifyReviewersOnReportCreated
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $report = $event->report;

        $title = 'Новий звіт на розгляді';
        $body = e($report->submitter?->name ?? $report->user?->name ?? 'Учасник').' подав звіт — потребує розгляду.';

        $button = Route::has('admin.reports.index')
            ? ['text' => '📋  Список звітів', 'url' => route('admin.reports.index')]
            : null;

        User::permission('reports.manage')->get()->each(
            fn (User $reviewer) => $this->notifications->notify($reviewer, 'report_created', $title, $body, $button)
        );
    }
}
