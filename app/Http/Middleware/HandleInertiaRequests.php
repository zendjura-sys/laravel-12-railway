<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'can' => [
                'manageAddons' => $request->user()?->can('addons.manage') ?? false,
                'manageReports' => $request->user()?->can('reports.manage') ?? false,
                'manageProgression' => $request->user()?->can('progression.manage') ?? false,
                'manageSettings' => $request->user()?->can('settings.manage') ?? false,
                'manageRoles' => $request->user()?->can('roles.manage') ?? false,
                'manageUsers' => $request->user()?->can('users.manage') ?? false,
            ],
        ];
    }
}
