<?php

namespace Addons\AiAssistant\Listeners;

use Addons\AiAssistant\Jobs\AnalyzeReportPhotosJob;
use App\Models\Setting;

class DispatchReportPhotoAnalysis
{
    public function handle($event): void
    {
        if (Setting::get('ai_reports_analysis_enabled') !== '1') {
            return;
        }

        AnalyzeReportPhotosJob::dispatch($event->report->id);
    }
}
