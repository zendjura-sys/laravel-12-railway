<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    /**
     * Функція можлива лише з SESSION_DRIVER=database (тільки там чужі
     * сесії взагалі видно рядками в таблиці) — тести самі перемикають
     * конфіг, бо phpunit.xml навмисно ганяє решту сюїти на array-драйвері.
     */
    public function test_other_sessions_count_is_zero_without_the_database_session_driver(): void
    {
        config(['session.driver' => 'array']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertInertia(fn ($page) => $page->where('otherActiveSessions', 0));
    }

    /**
     * Тестовий HTTP-клієнт Laravel не тримає той самий session-id між
     * окремими викликами $this->get()/delete() так, як реальний браузер із
     * кукою, — тому тут перевіряємо не "чужі" рядки за конкретним id, а
     * межі логіки: рядки ІНШОГО користувача SQL-запит не займає взагалі,
     * і рядок, вставлений напряму (гарантовано "чужий" для будь-якого
     * id поточного тестового запиту), видаляється.
     */
    public function test_other_active_sessions_are_counted_with_the_database_driver(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'payload' => 'irrelevant',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertInertia(fn ($page) => $page->where('otherActiveSessions', 1));
    }

    public function test_expired_sessions_are_not_counted(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'expired-session-id',
            'user_id' => $user->id,
            'payload' => 'irrelevant',
            'last_activity' => now()->subMinutes(200)->getTimestamp(),
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertInertia(fn ($page) => $page->where('otherActiveSessions', 0));
    }

    public function test_a_user_can_log_out_other_sessions_with_their_password(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $otherUser = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'payload' => 'irrelevant',
            'last_activity' => now()->getTimestamp(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'unrelated-user-session',
            'user_id' => $otherUser->id,
            'payload' => 'irrelevant',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($user)
            ->delete('/profile/other-sessions', ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-user-session']);
    }

    public function test_logging_out_other_sessions_requires_the_correct_password(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => bcrypt('password')]);

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'payload' => 'irrelevant',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($user)
            ->delete('/profile/other-sessions', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('sessions', ['id' => 'other-session-id']);
    }
}
