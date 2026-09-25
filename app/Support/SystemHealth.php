<?php

namespace App\Support;

use App\Models\FailedJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Стан сервера на дашборді адмінки — щоб не лізти по SSH перевірити, чи
 * не забилась черга й чи не запинився бекап. Усе тут best-effort: кожен
 * показник мовчки повертає null/0, якщо не може прочитати джерело (немає
 * прав, файлу ще нема), а не валить сторінку.
 */
class SystemHealth
{
    public static function failedJobsCount(): int
    {
        return FailedJob::query()->count();
    }

    public static function queuedJobsCount(): int
    {
        return DB::table('jobs')->count();
    }

    /** @return array{freeGb: float, totalGb: float}|null */
    public static function disk(): ?array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false) {
            return null;
        }

        return [
            'freeGb' => round($free / 1024 ** 3, 1),
            'totalGb' => round($total / 1024 ** 3, 1),
        ];
    }

    /**
     * deploy/backup.sh пише сюди маленький маркер після успішного
     * завершення — сам файл бекапу лежить у /root/backups, куди www-data
     * доступу не має (і не повинен мати).
     */
    public static function lastBackupAt(): ?Carbon
    {
        $path = storage_path('app/backup-status.json');

        if (! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $finishedAt = $data['finished_at'] ?? null;

        if (! $finishedAt) {
            return null;
        }

        try {
            return Carbon::parse($finishedAt);
        } catch (\Throwable) {
            return null;
        }
    }
}
