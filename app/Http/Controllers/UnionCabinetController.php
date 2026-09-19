<?php

namespace App\Http\Controllers;

use App\Models\UnionAnnouncement;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Особистий кабінет союзника — те, що бачить на /dashboard зареєстрований
 * через union.monsory.net (авторизація спільна з рештою сайту, тому
 * маршрут той самий: routes/web.php вирішує, кому яку сторінку віддати,
 * за union_family_name). Профіль і форми редагування — ProfileController,
 * тут лише огляд: своя родина, союз загалом, стрічка оголошень.
 */
class UnionCabinetController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $familyMembers = User::query()
            ->where('union_family_name', $user->union_family_name)
            ->orderByRaw("case when union_role = 'leader' then 0 when union_role = 'deputy' then 1 else 2 end")
            ->orderBy('name')
            ->get(['id', 'name', 'union_role', 'created_at']);

        return Inertia::render('Union/Cabinet', [
            'family' => [
                'name' => $user->union_family_name,
                'members' => $familyMembers,
            ],
            'unionStats' => [
                'families' => User::query()->whereNotNull('union_family_name')->distinct('union_family_name')->count('union_family_name'),
                'members' => User::query()->whereNotNull('union_family_name')->count(),
            ],
            'announcements' => UnionAnnouncement::query()
                ->where('published_at', '<=', now())
                ->with('author:id,name')
                ->orderByDesc('published_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
