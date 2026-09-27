<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Розділ «Союз» в адмінці: скарги (union.manage — повний доступ, модерація)
 * та оголошення (CRUD). ЧСС/ЧС — окремий UnionBlacklistTest.
 */
class UnionAdminSectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'union.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_index_page_is_gated_by_union_manage(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.union.index'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.union.index'))
            ->assertOk();
    }

    public function test_admin_can_moderate_a_complaint(): void
    {
        $reporter = User::factory()->create();
        $complaint = \App\Models\UnionComplaint::create([
            'reporter_id' => $reporter->id,
            'against_family' => 'Family Del Rio',
            'against_name' => 'Порушник',
            'reasons' => ['rdm'],
            'status' => 'pending',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.union.complaints.update', $complaint), [
                'status' => 'resolved',
                'reviewer_note' => 'Розглянуто, підтверджено.',
            ])
            ->assertRedirect();

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame($admin->id, $complaint->reviewed_by);
        $this->assertNotNull($complaint->reviewed_at);
    }

    public function test_admin_can_publish_an_announcement(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.union.announcements.store'), [
                'title' => 'Нове партнерство',
                'body' => 'Союз розширюється.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('union_announcements', ['title' => 'Нове партнерство']);
    }
}
