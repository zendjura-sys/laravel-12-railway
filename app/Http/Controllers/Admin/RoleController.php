<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name'),
            ]);

        $permissions = Permission::query()->orderBy('name')->pluck('name');

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')],
        ]);

        Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        return back()->with('status', 'role-created');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', 'role-updated');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Роль "admin" — несущая конструкция всей системы прав; удалить
        // её означало бы мгновенно лишить всех админов доступа без
        // возможности зайти и создать её обратно через UI.
        abort_if($role->name === 'admin', 403, 'Роль "admin" не можна видалити.');

        $role->delete();

        return back()->with('status', 'role-deleted');
    }
}
