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

        $this->assertSame(config('family.positions')[0]['key'], $user->position_key);
        $this->assertSame(config('family.positions')[0]['title'], $user->position_title);
    }

    public function test_position_title_is_null_without_an_assigned_position(): void
    {
        $user = User::factory()->create(['position_key' => null]);

        $this->assertNull($user->position_title);
    }

    /** Невідомий/видалений ключ — це «не призначено», а не помилка чи сирі дані. */
    public function test_unknown_key_resolves_to_null(): void
    {
        $user = User::factory()->create(['position_key' => 'not-a-real-position']);

        $this->assertNull($user->position_title);
    }

    /**
     * Перейменування посади в адмінці не рве прив'язку — ключ той самий,
     * міняється тільки текст.
     */
    public function test_position_title_follows_a_renamed_title_by_key(): void
    {
        $user = User::factory()->create(['position_key' => 'specialist']);

        $this->assertSame('Спеціаліст', $user->position_title);

        $positions = config('family.positions');
        $positions[2]['title'] = 'Досвідчений боєць';
        Setting::set('positions', json_encode($positions, JSON_UNESCAPED_UNICODE), 'content');

        $this->assertSame('Досвідчений боєць', $user->fresh()->position_title);
    }

    /**
     * Головна причина, чому це взагалі key, а не index: у Дизайн →
     * Розділи посади можна переставляти стрілками ↑/↓ — звичайна дія
     * адміна. При зберіганні індексом ця перестановка мовчки
     * переприсвоювала б людині чужу посаду.
     */
    public function test_position_title_survives_reordering_in_admin_content(): void
    {
        $user = User::factory()->create(['position_key' => 'director']);
        $this->assertSame('Директор', $user->position_title);

        // Переставляємо: director (був останнім) тепер перший.
        $positions = config('family.positions');
        $director = array_pop($positions);
        array_unshift($positions, $director);
        Setting::set('positions', json_encode($positions, JSON_UNESCAPED_UNICODE), 'content');

        $this->assertSame('Директор', $user->fresh()->position_title);
    }

    /**
     * Видалення посади, яка КОМУСЬ призначена, не повинно мовчки
     * підсунути іншу назву — тільки чесне «не призначено».
     */
    public function test_removing_an_assigned_position_falls_back_to_unassigned(): void
    {
        $user = User::factory()->create(['position_key' => 'director']);

        $positions = array_values(array_filter(
            config('family.positions'),
            fn (array $p) => $p['key'] !== 'director',
        ));
        Setting::set('positions', json_encode($positions, JSON_UNESCAPED_UNICODE), 'content');

        $this->assertNull($user->fresh()->position_title);
    }

    /** Нова посада, додана в адмінці без явного ключа, отримує згенерований. */
    public function test_a_position_added_without_a_key_gets_one_generated(): void
    {
        FamilyContent::save('positions', [
            ['title' => 'Новий підрозділ', 'text' => 'Опис', 'image' => ''],
        ]);

        $saved = FamilyContent::positions();

        $this->assertCount(1, $saved);
        $this->assertNotSame('', $saved[0]['key']);
        $this->assertSame($saved[0]['key'], FamilyContent::positionByKey($saved[0]['key'])['key']);
    }

    /** Дві посади з однаковою назвою не повинні отримати однаковий ключ. */
    public function test_duplicate_titles_get_distinct_keys(): void
    {
        FamilyContent::save('positions', [
            ['title' => 'Новий підрозділ', 'text' => 'A', 'image' => ''],
            ['title' => 'Новий підрозділ', 'text' => 'B', 'image' => ''],
        ]);

        $saved = FamilyContent::positions();

        $this->assertCount(2, $saved);
        $this->assertNotSame($saved[0]['key'], $saved[1]['key']);
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
        $member = User::factory()->create(['position_key' => null]);

        $response = $this->actingAs($admin)->put(
            route('admin.users.position', $member),
            ['position_key' => 'senior-manager'],
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame('senior-manager', $member->fresh()->position_key);
    }

    public function test_admin_can_clear_a_position(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['position_key' => 'manager']);

        $this->actingAs($admin)->put(route('admin.users.position', $member), ['position_key' => null]);

        $this->assertNull($member->fresh()->position_key);
    }

    /** Ключ, якого немає в поточному списку, — відмова, а не сирий запис у БД. */
    public function test_unknown_key_is_refused(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $response = $this->actingAs($admin)->put(
            route('admin.users.position', $member),
            ['position_key' => 'made-up-key-that-does-not-exist'],
        );

        $response->assertSessionHasErrors('position_key');
    }

    public function test_a_member_without_permission_cannot_assign_positions(): void
    {
        $this->seed(SystemPermissionsSeeder::class);
        $plain = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($plain)
            ->put(route('admin.users.position', $target), ['position_key' => 'assistant'])
            ->assertForbidden();
    }

    /** Підвищення відбуваються в грі — учасник сам оновлює собі посаду на сайті. */
    public function test_a_member_can_set_their_own_position(): void
    {
        $member = User::factory()->create(['position_key' => 'trainee']);

        $response = $this->actingAs($member)->patch(route('profile.position'), [
            'position_key' => 'senior-manager',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('senior-manager', $member->fresh()->position_key);
    }

    public function test_a_member_can_clear_their_own_position(): void
    {
        $member = User::factory()->create(['position_key' => 'manager']);

        $this->actingAs($member)->patch(route('profile.position'), ['position_key' => null]);

        $this->assertNull($member->fresh()->position_key);
    }

    /** Ключ, якого немає в поточному списку, — відмова і для самостійної зміни теж. */
    public function test_a_member_cannot_set_an_unknown_position_key(): void
    {
        $member = User::factory()->create(['position_key' => 'trainee']);

        $response = $this->actingAs($member)->patch(route('profile.position'), [
            'position_key' => 'made-up-key-that-does-not-exist',
        ]);

        $response->assertSessionHasErrors('position_key');
        $this->assertSame('trainee', $member->fresh()->position_key);
    }

    public function test_a_guest_cannot_set_a_position(): void
    {
        $this->patch(route('profile.position'), ['position_key' => 'trainee'])
            ->assertRedirect(route('login'));
    }
}
