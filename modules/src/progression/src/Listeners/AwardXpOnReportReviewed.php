<?php

namespace Addons\Progression\Listeners;

use Addons\Progression\Services\ProgressionService;
use Addons\Reports\Events\ReportReviewed;

class AwardXpOnReportReviewed
{
    public function __construct(private readonly ProgressionService $service)
    {
    }

    public function handle(ReportReviewed $event): void
    {
        // pending -> approved уже произошёл к этому моменту (событие шлётся
        // после update()), rejected — не начисляем ничего.
        if ($event->report->status !== 'approved') {
            return;
        }

        $this->service->handleApprovedReport($event->report);
    }
}
