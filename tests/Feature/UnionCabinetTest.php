<?php

namespace Tests\Feature;

use App\Models\UnionAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /dashboard — спільна адреса: союзник (union_family_name заповнене
 * реєстрацією через union.monsory.net) бачить Union/Cabinet, решта — Dashboard.
 */
class UnionCabinetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_union_member_sees_the_union_cabinet_on_dashboard(): void
    {
        $user = User::factory()->create(['union_family_name' => 'Family Corvo', 'union_role' => 'leader']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Union/Cabinet', shouldExist: false)
                ->where('family.name', 'Family Corvo'));
    }

    public function test_a_regular_family_member_sees_the_normal_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard', shouldExist: false));
    }

    public function test_the_union_cabinet_lists_family_members_and_published_announcements(): void
    {
        $user = User::factory()->create(['union_family_name' => 'Family Corvo', 'union_role' => 'leader']);
        User::factory()->create(['union_family_name' => 'Family Corvo', 'union_role' => 'member']);
        User::factory()->create(['union_family_name' => 'Family Del Rio', 'union_role' => 'member']);

        UnionAnnouncement::create(['title' => 'Опубліковано', 'body' => 'x', 'published_at' => now()->subDay()]);
        UnionAnnouncement::create(['title' => 'Майбутнє', 'body' => 'x', 'published_at' => now()->addDay()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('family.members', 2)
                ->where('unionStats.families', 2)
                ->where('unionStats.members', 3)
                ->has('announcements', 1)
                ->where('announcements.0.title', 'Опубліковано'));
    }
}
