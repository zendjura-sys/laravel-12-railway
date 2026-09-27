<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /register не мав жодного обмеження частоти — на відміну від
 * логіну (там власний лічильник у LoginRequest), реєстрацію можна було
 * спамити скільки завгодно. throttle:6,1 (6 спроб/хв) — те саме
 * обмеження, що вже стоїть на підтвердженні email.
 */
class RegistrationThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_rate_limited(): void
    {
        // Навмисно НЕВАЛІДНІ дані (пароль без підтвердження) — щоб жодна
        // спроба не залогінила тестовий клієнт: успішна реєстрація одразу
        // авторизує, і 'guest'-мідлвар на маршруті почав би просто
        // редіректити далі БЕЗ участі throttle, а не бо лічильник спрацював.
        $attempt = fn (string $email) => $this->post('/register', [
            'first_name' => 'Spam',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'not-matching',
        ]);

        for ($i = 0; $i < 6; $i++) {
            $attempt("spam{$i}@example.com")->assertStatus(302)->assertSessionHasErrors('password');
        }

        $attempt('spam-overflow@example.com')->assertStatus(429);
    }
}
