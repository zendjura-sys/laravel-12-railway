<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserAuditLog;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * admin.users.update/reset-password/destroy — додані для кнопки
 * "редагувати учасника" в адмінці. Раніше жодного з них не тестувалось:
 * саме тут живуть запобіжники (не видалити себе, не лишити родину без
 * жодного admin) і побічні ефекти (email_verified_at, журнал дій), які
 * легко зламати непомітно при рефакторингу.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(SystemPermissionsSeeder::class);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_update_a_member_profile(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $member), [
            'first_name' => 'Tommy',
            'last_name' => 'Vercetti',
            'email' => $member->email,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'user-updated']);
        $this->assertSame('Tommy Vercetti', $member->fresh()->name);
    }

    public function test_changing_email_resets_verification(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->put(route('admin.users.update', $member), [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => 'new-address@example.com',
        ]);

        $this->assertNull($member->fresh()->email_verified_at);
    }

    public function test_updating_a_member_writes_an_audit_log_entry(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->put(route('admin.users.update', $member), [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
        ]);

        $this->assertDatabaseHas('user_audit_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $member->id,
            'action' => 'profile_updated',
        ]);
    }

    public function test_admin_can_reset_a_member_password_and_sees_it_once(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('old-password')]);

        $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $member));

        $response->assertOk();
        $newPassword = $response->json('data.password');
        $this->assertNotEmpty($newPassword);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($newPassword, $member->fresh()->password));

        $this->assertDatabaseHas('user_audit_logs', [
            'actor_id' => $admin->id,
            'target_user_id' => $member->id,
            'action' => 'password_reset',
        ]);
    }

    public function test_admin_cannot_delete_their_own_account_from_this_screen(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertStatus(422);
        $this->assertNotNull($admin->fresh());
    }

    public function test_the_last_admin_account_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin->fresh()));

        $response->assertStatus(422);
    }

    public function test_admin_can_delete_another_members_account(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $member));

        $response->assertOk();
        $this->assertNull(User::find($member->id));
    }

    public function test_deleting_a_member_keeps_their_name_in_the_audit_log(): void
    {
        $admin = $this->admin();
        // name — похідне від first_name+last_name (User::booted() перебудовує
        // його при кожному збереженні), тому напряму 'name' у фабриці не
        // прижилось б.
        $member = User::factory()->create(['first_name' => 'Deleted', 'last_name' => 'Member', 'email_verified_at' => now()]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member));

        $log = UserAuditLog::where('action', 'account_deleted')->first();
        $this->assertNotNull($log);
        $this->assertSame('Deleted Member', $log->meta['name'] ?? null);
        // nullOnDelete: зв'язок з видаленим користувачем зникає, лишається лише meta.
        $this->assertNull($log->target_user_id);
    }

    public function test_a_second_admin_can_be_deleted_when_more_than_one_exists(): void
    {
        $admin = $this->admin();
        $secondAdmin = User::factory()->create(['email_verified_at' => now()]);
        $secondAdmin->assignRole('admin');

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $secondAdmin));

        $response->assertOk();
        $this->assertNull(User::find($secondAdmin->id));
    }
}
