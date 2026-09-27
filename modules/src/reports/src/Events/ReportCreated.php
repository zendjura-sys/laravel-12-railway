<?php

namespace Addons\Reports\Events;

use Addons\Reports\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * report.created — фиксация факта подачи отчёта.
 *
 * Слушатели других аддонов (Progression, Family Goals) подключаются
 * через свою entrypoints.events, например:
 *   Event::listen(\Addons\Reports\Events\ReportCreated::class, ...);
 * Именно так задумана межмодульная интеграция без прямых зависимостей
 * между пакетами.
 */
class ReportCreated
{
    use Dispatchable;

    public function __construct(public readonly Report $report)
    {
    }
}
