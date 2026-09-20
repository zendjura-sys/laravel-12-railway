<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FamilyStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Мінімальна нативна адмінка в мобільному застосунку — лише ядро (жодних
 * аддон-специфічних дій на кшталт модерації звітів чи скасування
 * переказів, для цього лишається повна адмінка на сайті). Дає той, хто
 * керує сайтом, побачити ключові цифри й склад родини, не відкриваючи
 * браузер.
 */
class AdminController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        abort_unless($this->hasAnyManagePermission($request->user()), 403);

        return response()->json(['stats' => FamilyStats::available()]);
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(30)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->position_title,
                'roles' => $user->roles->pluck('name'),
                'avatarUrl' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
                'isShadow' => $user->is_shadow,
            ]);

        return response()->json([
            'users' => $users->items(),
            'currentPage' => $users->currentPage(),
            'lastPage' => $users->lastPage(),
        ]);
    }

    private function hasAnyManagePermission(User $user): bool
    {
        return $user->getAllPermissions()->pluck('name')->contains(
            fn (string $permission) => str_ends_with($permission, '.manage'),
        );
    }
}
