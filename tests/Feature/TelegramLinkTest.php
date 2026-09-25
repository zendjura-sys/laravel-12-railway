<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\TelegramLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес уходит прямо в href кнопок лендинга, а Vue в :href ничего не
     * санитайзит. Значение со схемой javascript: выполнилось бы в origin
     * сайта у каждого, кто нажмёт «Подати заявку».
     */
    public function test_non_http_schemes_are_refused(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 't.me/bot', '#', 'ftp://x'] as $bad) {
            Setting::set('telegram_bot_url', $bad, 'telegram');
            Setting::set('telegram_bot_username', null, 'telegram');

            $this->assertNull(TelegramLink::url(), "схема {$bad} мала бути відкинута");
        }
    }

    public function test_http_url_is_accepted(): void
    {
        Setting::set('telegram_bot_url', 'https://t.me/monsory_bot', 'telegram');

        $this->assertSame('https://t.me/monsory_bot', TelegramLink::url());
    }

    /** Ссылку собираем из username, чтобы её не приходилось вписывать дважды. */
    public function test_url_is_derived_from_the_username(): void
    {
        Setting::set('telegram_bot_url', null, 'telegram');
        Setting::set('telegram_bot_username', '@monsory_bot', 'telegram');

        $this->assertSame('https://t.me/monsory_bot', TelegramLink::url());
    }

    /** Ничего не настроено — страница должна показать альтернативу, а не мёртвую кнопку. */
    public function test_returns_null_when_nothing_is_configured(): void
    {
        $this->assertNull(TelegramLink::url());
    }

    public function test_settings_form_rejects_a_javascript_url(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('admin.settings.update', 'telegram'), [
            'telegram_bot_url' => 'javascript:alert(1)',
        ]);

        $response->assertSessionHasErrors('telegram_bot_url');
        $this->assertNull(Setting::get('telegram_bot_url'));
    }

    private function makeAdmin(): \App\Models\User
    {
        $this->seed(\Database\Seeders\SystemPermissionsSeeder::class);

        $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }
}
