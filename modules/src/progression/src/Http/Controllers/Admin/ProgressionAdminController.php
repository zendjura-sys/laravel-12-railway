<?php

namespace Addons\Progression\Http\Controllers\Admin;

use Addons\Progression\Models\ProgressionProfile;
use Addons\Progression\Services\ProgressionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressionAdminController
{
    public function __construct(private readonly ProgressionService $service)
    {
    }

    public function index(): Response
    {
        $profiles = ProgressionProfile::with('user:id,name,email')
            ->orderByDesc('xp')
            ->paginate(25);

        return Inertia::render('Admin/Progression/Index', ['profiles' => $profiles]);
    }

    /** Ручная корректировка — та самая точка входа, которую в будущем будет вызывать Member Center. */
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($data['user_id']);

        $this->service->awardXp(
            $user,
            $data['amount'],
            $data['reason'],
            'manual',
            awardedBy: $request->user(),
        );

        return response()->json([
            'ok' => true,
            'message' => "XP скориговано для {$user->name}.",
            'data' => ['profile' => $this->service->profileFor($user)->fresh()],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
