<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\User;
use App\Support\SystemHealth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard/Index', [
            'stats' => [
                'members' => User::query()->count(),
                'roles' => Role::query()->count(),
                'activeAddons' => Addon::query()->where('status', 'active')->count(),
            ],
            // Технічні показники бачить лише той, хто й так керує
            // налаштуваннями сайту — не варто чіпати модератору звітів.
            'health' => $request->user()->can('settings.manage') ? [
                'failedJobs' => SystemHealth::failedJobsCount(),
                'queuedJobs' => SystemHealth::queuedJobsCount(),
                'disk' => SystemHealth::disk(),
                'lastBackupAt' => SystemHealth::lastBackupAt()?->toIso8601String(),
            ] : null,
        ]);
    }
}
