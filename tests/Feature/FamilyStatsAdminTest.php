<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Редактор статистики на Admin Dashboard — обирає, ЩО з FamilyStats::available() показує головна сторінка. */
class FamilyStatsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_dashboard_exposes_available_metrics_and_current_selection(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('familyStats.available')
                ->has('familyStats.selectedKeys'));
    }

    public function test_a_moderator_without_settings_manage_does_not_see_the_editor(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'reports.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('familyStats', null));
    }

    public function test_admin_can_save_the_selection(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.stats.update'), ['keys' => ['union_members', 'members']])
            ->assertRedirect();

        $this->assertSame(['union_members', 'members'], \App\Support\FamilyStats::selectedKeys());
    }

    public function test_a_member_without_settings_manage_cannot_save_the_selection(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.stats.update'), ['keys' => ['members']])
            ->assertForbidden();
    }

    public function test_the_public_homepage_shows_the_saved_selection(): void
    {
        \App\Support\FamilyStats::save(['union_members']);
        User::factory()->create(['union_family_name' => 'Family Corvo']);

        $this->get('http://localhost/')
            ->assertInertia(fn ($page) => $page
                ->has('stats', 1)
                ->where('stats.0.key', 'union_members')
                ->where('stats.0.value', 1));
    }
}
