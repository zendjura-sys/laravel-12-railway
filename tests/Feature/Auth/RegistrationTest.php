<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * Регрессия на поломку, с которой начиналась работа: письмо с
     * подтверждением не уходило вообще. Две причины, обе молчаливые —
     * User не объявлял MustVerifyEmail, и слушатель Registered нигде не
     * регистрировался (в Laravel 12 нет EventServiceProvider по
     * умолчанию). Сайт при этом вёл себя как исправный.
     */
    public function test_registration_sends_the_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Tommy',
            'last_name' => 'Vercetti',
            'email' => 'tommy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Notification::assertSentTo(
            User::where('email', 'tommy@example.com')->firstOrFail(),
            VerifyEmail::class,
        );
    }

    /** name собирается из имени и фамилии, на него завязано всё отображение. */
    public function test_full_name_is_composed_from_first_and_last_name(): void
    {
        $this->post('/register', [
            'first_name' => 'Tommy',
            'last_name' => 'Vercetti',
            'email' => 'tommy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame('Tommy Vercetti', User::where('email', 'tommy@example.com')->value('name'));
    }

    /** Фамилия необязательна: часть участников известна одним ником. */
    public function test_last_name_is_optional(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Solo',
            'email' => 'solo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Solo', User::where('email', 'solo@example.com')->value('name'));
    }
}
