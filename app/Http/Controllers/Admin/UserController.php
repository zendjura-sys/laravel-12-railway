<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'search' => $search,
        ]);
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        // Останній admin у системі не можна роздягнути через UI — інакше
        // легко втратити доступ до самої адмінки без ручного втручання в БД.
        if (
            $user->hasRole('admin')
            && ! in_array('admin', $data['roles'] ?? [], true)
            && User::role('admin')->count() <= 1
        ) {
            return back()->withErrors(['roles' => 'Це останній акаунт з роллю "admin" — не можна забрати роль.']);
        }

        $user->syncRoles($data['roles'] ?? []);

        return back()->with('status', 'roles-updated');
    }
}
