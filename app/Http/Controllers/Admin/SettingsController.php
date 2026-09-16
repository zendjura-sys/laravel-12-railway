<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** @var array<string, array<int, string>> */
    private const FIELDS = [
        'general' => ['site_name', 'site_tagline', 'support_contact'],
        'telegram' => ['telegram_bot_username', 'telegram_bot_token', 'telegram_webhook_url', 'telegram_bot_url', 'telegram_group_id'],
        'discord' => ['discord_client_id', 'discord_client_secret', 'discord_redirect_uri', 'discord_bot_token', 'discord_guild_id'],
    ];

    /**
     * Поля, куда можно положить только http(s)-адрес.
     *
     * Без этого в telegram_bot_url принималась любая строка, а оттуда она
     * уезжает прямо в href главной кнопки лендинга. Vue в :href ничего не
     * санитайзит, поэтому значение вида javascript:… выполнялось бы в
     * origin сайта у каждого, кто нажмёт «Подати заявку». Ограничение на
     * стороне сервера, а не шаблона: потребителей у настройки несколько.
     *
     * @var array<int, string>
     */
    private const URL_FIELDS = [
        'telegram_webhook_url', 'telegram_bot_url', 'discord_redirect_uri',
    ];

    public function index(): Response
    {
        $values = [];
        foreach (self::FIELDS as $group => $keys) {
            foreach ($keys as $key) {
                $values[$key] = Setting::get($key);
            }
        }

        return Inertia::render('Admin/Settings/Index', [
            'values' => $values,
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, self::FIELDS), 404);

        $rules = [];
        foreach (self::FIELDS[$group] as $key) {
            $rules[$key] = in_array($key, self::URL_FIELDS, true)
                ? ['nullable', 'string', 'max:2000', 'url:http,https']
                : ['nullable', 'string', 'max:2000'];
        }

        $data = $request->validate($rules, [], [
            'telegram_bot_url' => 'посилання на бота',
            'telegram_webhook_url' => 'webhook URL',
            'discord_redirect_uri' => 'Redirect URI',
        ]);

        foreach (self::FIELDS[$group] as $key) {
            Setting::set($key, $data[$key] ?? null, $group);
        }

        return back()->with('status', 'settings-updated');
    }
}
