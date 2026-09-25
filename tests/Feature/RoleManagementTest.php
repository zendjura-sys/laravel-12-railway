<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * admin.roles.update/destroy викликаються сирим axios, не через
 * Inertia-роутер — тому мають повертати JSON, а не редірект: back() тут
 * давав 302 без заголовка X-Inertia, який браузер реплеїв тим самим
 * методом на Referer (/admin/roles без id, лише GET) одразу після
 * успішного збереження. Див. той самий фікс для admin.users.roles/position.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(SystemPermissionsSeeder::class);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_updating_role_permissions_returns_json_not_a_redirect(): void
    {
        $admin = $this->admin();
        Permission::firstOrCreate(['name' => 'goals.manage', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'moderator', 'guard_name' => 'web']);

        $response = $this->actingAs($admin)->put(
            route('admin.roles.update', $role),
            ['permissions' => ['goals.manage']],
        );

        $response->assertOk();
        $response->assertJson(['status' => 'role-updated']);
        $this->assertTrue($role->fresh()->hasPermissionTo('goals.manage'));
    }

    public function test_deleting_a_role_returns_json_not_a_redirect(): void
    {
        $admin = $this->admin();
        $role = Role::create(['name' => 'moderator', 'guard_name' => 'web']);

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $role));

        $response->assertOk();
        $response->assertJson(['status' => 'role-deleted']);
        $this->assertNull(Role::find($role->id));
    }

    public function test_the_admin_role_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $adminRole))
            ->assertForbidden();

        $this->assertNotNull(Role::find($adminRole->id));
    }
}
