<?php

namespace Addons\Reports\Events;

use Addons\Reports\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * report.reviewed — отчёт прошёл модерацию (approved/rejected).
 * XP/прогресс должны начисляться по ЭТОМУ событию с status=approved,
 * а не по report.created — pending-отчёты ни на что не влияют.
 */
class ReportReviewed
{
    use Dispatchable;

    public function __construct(public readonly Report $report)
    {
    }
}
