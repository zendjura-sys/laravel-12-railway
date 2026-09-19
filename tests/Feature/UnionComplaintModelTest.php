<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UnionComplaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Скарги союзу — БД-шар: UnionComplaint::visibleTo() віддає різний набір
 * залежно від того, хто дивиться (Monsory-адмін бачить усе, лідер/заступник
 * родини — лише скарги проти своєї родини, звичайний учасник — нічого).
 */
class UnionComplaintModelTest extends TestCase
{
    use RefreshDatabase;

    private function complaint(string $family): UnionComplaint
    {
        $reporter = User::factory()->create();

        return UnionComplaint::create([
            'reporter_id' => $reporter->id,
            'against_family' => $family,
            'against_name' => 'Порушник',
            'reasons' => ['rdm', 'disrespect'],
            'description' => 'Тестовий опис',
            'status' => 'pending',
        ]);
    }

    public function test_reasons_are_keyed_with_label_and_description(): void
    {
        $this->assertArrayHasKey('rdm', UnionComplaint::REASONS);
        $this->assertArrayHasKey('label', UnionComplaint::REASONS['rdm']);
        $this->assertArrayHasKey('description', UnionComplaint::REASONS['rdm']);
    }

    public function test_admin_sees_every_complaint(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'union.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->complaint('Family Del Rio');
        $this->complaint('Family Corvo');

        $this->assertSame(2, UnionComplaint::visibleTo($admin)->count());
    }

    public function test_family_leader_sees_only_complaints_against_own_family(): void
    {
        $leader = User::factory()->create(['union_family_name' => 'Family Del Rio', 'union_role' => 'leader']);

        $this->complaint('Family Del Rio');
        $this->complaint('Family Corvo');

        $visible = UnionComplaint::visibleTo($leader)->get();

        $this->assertCount(1, $visible);
        $this->assertSame('Family Del Rio', $visible->first()->against_family);
    }

    public function test_family_member_without_leader_role_sees_nothing(): void
    {
        $member = User::factory()->create(['union_family_name' => 'Family Del Rio', 'union_role' => 'member']);

        $this->complaint('Family Del Rio');

        $this->assertSame(0, UnionComplaint::visibleTo($member)->count());
    }

    public function test_outsider_without_union_family_sees_nothing(): void
    {
        $outsider = User::factory()->create();

        $this->complaint('Family Del Rio');

        $this->assertSame(0, UnionComplaint::visibleTo($outsider)->count());
    }
}
