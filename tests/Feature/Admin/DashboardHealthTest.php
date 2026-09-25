<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Стан сервера на дашборді бачить лише той, хто й так керує налаштуваннями
 * — тим, у кого лише окреме право (напр. reports.manage), він ні до чого.
 */
class DashboardHealthTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $permission): User
    {
        $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => $permission.'-role', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_a_settings_manager_sees_the_health_widget(): void
    {
        $this->actingAs($this->userWithPermission('settings.manage'))
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('health')
                ->where('health.failedJobs', 0)
                ->where('health.queuedJobs', 0));
    }

    public function test_a_user_without_settings_permission_does_not_see_it(): void
    {
        $this->actingAs($this->userWithPermission('reports.manage'))
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->where('health', null));
    }
}
