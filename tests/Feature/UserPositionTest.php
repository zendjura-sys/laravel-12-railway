<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\FamilyContent;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPositionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * "Починають усі однаково — зі Стажера" — та ж формулювання, що на
     * сайті й у боті. Перевіряємо, що реєстрація її й справді виконує,
     * а не тільки обіцяє текстом.
     */
    public function test_new_registration_starts_at_the_first_position(): void
    {
        $this->post('/register', [
            'first_name' => 'Nova',
            'email' => 'nova@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'nova@example.com')->firstOrFail();

        $this->assertSame(0, $user->position_index);
        $this->assertSame(config('family.positions')[0]['title'], $user->position_title);
    }

    public function test_position_title_is_null_without_an_assigned_position(): void
    {
        $user = User::factory()->create(['position_index' => null]);

        $this->assertNull($user->position_title);
    }

    /**
     * Індекс береться з живого списку FamilyContent, а не заморожується
     * текстом: якщо адмін перейменує посаду, вона лишається прив'язаною
     * коректно.
     */
    public function test_position_title_follows_admin_edited_content(): void
    {
        $user = User::factory()->create(['position_index' => 2]);

        $this->assertSame('Спеціаліст', $user->position_title);

        $positions = config('family.positions');
        $positions[2]['title'] = 'Досвідчений боєць';
        Setting::set('positions', json_encode($positions, JSON_UNESCAPED_UNICODE), 'content');

        $this->assertSame('Досвідчений боєць', $user->fresh()->position_title);
    }

    /** Индекс за межами списку — как «не призначено», а не помилка чи сирі дані. */
    public function test_out_of_range_index_resolves_to_null(): void
    {
        $user = User::factory()->create(['position_index' => 250]);

        $this->assertNull($user->position_title);
    }

    private function admin(): User
    {
        $this->seed(SystemPermissionsSeeder::class);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_assign_a_position(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['position_index' => null]);

        $response = $this->actingAs($admin)->put(
            route('admin.users.position', $member),
            ['position_index' => 4],
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(4, $member->fresh()->position_index);
    }

    public function test_admin_can_clear_a_position(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['position_index' => 3]);

        $this->actingAs($admin)->put(route('admin.users.position', $member), ['position_index' => null]);

        $this->assertNull($member->fresh()->position_index);
    }

    /** Список — рівно 10 посад; індекс поза межами мав би зламати сайт і бота. */
    public function test_index_beyond_the_position_list_is_refused(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $max = count(FamilyContent::positions());

        $response = $this->actingAs($admin)->put(
            route('admin.users.position', $member),
            ['position_index' => $max],
        );

        $response->assertSessionHasErrors('position_index');
    }

    public function test_a_member_without_permission_cannot_assign_positions(): void
    {
        $this->seed(SystemPermissionsSeeder::class);
        $plain = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($plain)
            ->put(route('admin.users.position', $target), ['position_index' => 1])
            ->assertForbidden();
    }
}
