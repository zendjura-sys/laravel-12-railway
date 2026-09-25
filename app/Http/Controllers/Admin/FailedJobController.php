<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FailedJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Без цього провалена чергова джоба (розсилка в Telegram, тижневі премії
 * тощо) просто зникає в failed_jobs без жодного сліду — ніхто не дізнається,
 * поки хтось не поскаржиться, що сповіщення не прийшло. Ретрай і видалення
 * йдуть через ті самі artisan-команди (`queue:retry`/`queue:forget`), що й
 * з консолі — щоб не дублювати логіку повторної постановки в чергу.
 */
class FailedJobController extends Controller
{
    public function index(): Response
    {
        $jobs = FailedJob::query()
            ->orderByDesc('failed_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FailedJob $job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'job_name' => $job->jobName(),
                'exception_summary' => $job->exceptionSummary(),
                'exception' => $job->exception,
                'failed_at' => $job->failed_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/FailedJobs/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function retry(FailedJob $failedJob): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$failedJob->uuid]]);

        return back()->with('status', 'Джобу поставлено в чергу повторно.');
    }

    public function retryAll(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('status', 'Усі провалені джоби поставлено в чергу повторно.');
    }

    public function destroy(FailedJob $failedJob): RedirectResponse
    {
        $failedJob->delete();

        return back()->with('status', 'Запис видалено.');
    }

    public function clear(): RedirectResponse
    {
        FailedJob::query()->delete();

        return back()->with('status', 'Список провалених джоб очищено.');
    }
}
