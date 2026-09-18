<?php

namespace Addons\AiAssistant\Jobs;

use Addons\AiAssistant\Services\ReportPhotoAnalyzer;
use Addons\Reports\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * У черзі, а не синхронно в ReportController::store() — виклик Gemini з
 * фото займає кілька секунд, і подача звіту не повинна чекати на це.
 */
class AnalyzeReportPhotosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $reportId)
    {
    }

    public function handle(ReportPhotoAnalyzer $analyzer): void
    {
        $report = Report::find($this->reportId);
        if ($report) {
            $analyzer->analyze($report);
        }
    }
}
