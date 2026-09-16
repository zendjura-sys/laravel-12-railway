<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard/Index', [
            'stats' => [
                'members' => User::query()->count(),
                'roles' => Role::query()->count(),
                'activeAddons' => Addon::query()->where('status', 'active')->count(),
            ],
        ]);
    }
}
