<?php

namespace Tests\Feature;

use App\Models\UnionBlacklistedFamily;
use App\Models\UnionBlacklistedPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnionBlacklistTest extends TestCase
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

    private function unionMember(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'union_family_name' => 'Family Corvo',
            'union_role' => 'member',
        ], $overrides));
    }

    /* ---------- родини (адмін-only) ---------- */

    public function test_admin_can_blacklist_a_family_with_a_duration(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.union.blacklist.families.store'), [
                'family_name' => 'Family Del Rio',
                'reason' => 'Систематичний обман домовленостей.',
                'duration_hours' => 240,
            ])
            ->assertRedirect();

        $entry = UnionBlacklistedFamily::where('family_name', 'Family Del Rio')->firstOrFail();
        $this->assertTrue($entry->expires_at->isFuture());
        $this->assertSame(240, $entry->duration_hours);
    }

    public function test_a_regular_union_member_cannot_blacklist_a_family(): void
    {
        $this->actingAs($this->unionMember())
            ->post(route('admin.union.blacklist.families.store'), [
                'family_name' => 'x', 'reason' => 'x', 'duration_hours' => 1,
            ])
            ->assertForbidden();
    }

    public function test_duration_hours_is_bounded_between_one_hour_and_9999_days(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.union.blacklist.families.store'), [
                'family_name' => 'x', 'reason' => 'x', 'duration_hours' => 0,
            ])
            ->assertSessionHasErrors('duration_hours');

        $this->actingAs($admin)
            ->post(route('admin.union.blacklist.families.store'), [
                'family_name' => 'x', 'reason' => 'x', 'duration_hours' => 9999 * 24 + 1,
            ])
            ->assertSessionHasErrors('duration_hours');
    }

    /* ---------- гравці (будь-який союзник) ---------- */

    public function test_any_union_member_can_add_a_player_to_the_blacklist(): void
    {
        $this->actingAs($this->unionMember())
            ->post(route('union.blacklist.players.store'), [
                'first_name' => 'Порушник',
                'last_name' => 'Іванов',
                'family_name' => 'Family Del Rio',
                'reasons' => ['rdm', 'disrespect'],
                'description' => 'Постійно порушує правила рп.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('union_blacklisted_players', ['first_name' => 'Порушник', 'last_name' => 'Іванов']);
    }

    public function test_a_monsory_family_member_without_union_registration_cannot_add_a_player(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('union.blacklist.players.store'), [
                'first_name' => 'x', 'reasons' => ['rdm'],
            ])
            ->assertForbidden();
    }

    /* ---------- блокування реєстрації ---------- */

    public function test_a_blacklisted_family_cannot_register_on_the_union_domain(): void
    {
        UnionBlacklistedFamily::create([
            'family_name' => 'Family Del Rio',
            'reason' => 'x',
            'duration_hours' => 24,
            'expires_at' => now()->addDay(),
        ]);

        $this->post('http://union.monsory.test/register', [
            'first_name' => 'Новий', 'last_name' => 'Союзник',
            'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'union_family_name' => 'Family Del Rio', 'union_role' => 'member',
        ])->assertSessionHasErrors('union_family_name');

        $this->assertGuest();
    }

    public function test_an_expired_family_blacklist_entry_no_longer_blocks_registration(): void
    {
        UnionBlacklistedFamily::create([
            'family_name' => 'Family Del Rio',
            'reason' => 'x',
            'duration_hours' => 1,
            'expires_at' => now()->subHour(),
        ]);

        $this->post('http://union.monsory.test/register', [
            'first_name' => 'Новий', 'last_name' => 'Союзник',
            'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'union_family_name' => 'Family Del Rio', 'union_role' => 'member',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_a_blacklisted_player_cannot_register_on_the_union_domain(): void
    {
        UnionBlacklistedPlayer::create([
            'first_name' => 'Петро', 'last_name' => 'Гравцев', 'reasons' => ['rdm'],
        ]);

        $this->post('http://union.monsory.test/register', [
            'first_name' => 'Петро', 'last_name' => 'Гравцев',
            'email' => 'p@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'union_family_name' => 'Family Corvo', 'union_role' => 'member',
        ])->assertSessionHasErrors('first_name');

        $this->assertGuest();
    }

    public function test_a_blacklisted_player_cannot_register_on_the_main_family_domain_either(): void
    {
        UnionBlacklistedPlayer::create([
            'first_name' => 'Петро', 'last_name' => 'Гравцев', 'reasons' => ['rdm'],
        ]);

        $this->post('http://localhost/register', [
            'first_name' => 'Петро', 'last_name' => 'Гравцев',
            'email' => 'p2@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('first_name');

        $this->assertGuest();
    }
}
