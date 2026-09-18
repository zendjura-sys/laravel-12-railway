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
        'ai' => [
            'mistral_api_key',
            'mistral_proxy_url',
            'ai_reports_analysis_enabled',
            'ai_rejection_advice_enabled',
            'ai_grade_advice_enabled',
            'ai_applications_review_enabled',
            'ai_broadcast_assist_enabled',
        ],
        'ai_prompts' => [
            'ai_reports_analysis_instructions',
            'ai_rejection_advice_instructions',
            'ai_grade_advice_instructions',
            'ai_applications_review_instructions',
            'ai_broadcast_assist_instructions',
        ],
    ];

    /**
     * Довільні інструкції адміна для AI-промптів — на відміну від решти
     * текстових полів (назва сайту, URL тощо), тут очікується кілька речень
     * чи навіть абзаців, тому ліміт довжини значно ширший.
     *
     * @var array<int, string>
     */
    private const LONG_TEXT_FIELDS = [
        'ai_reports_analysis_instructions', 'ai_rejection_advice_instructions', 'ai_grade_advice_instructions',
        'ai_applications_review_instructions', 'ai_broadcast_assist_instructions',
    ];

    /**
     * Тумблери зберігаються як '1'/null через ту саму Setting::set(), що й
     * усе інше, — без окремої таблиці чи типу. На фронті це checkbox
     * (boolean), тому окремо конвертуємо в update().
     *
     * @var array<int, string>
     */
    private const BOOLEAN_FIELDS = [
        'ai_reports_analysis_enabled', 'ai_rejection_advice_enabled', 'ai_grade_advice_enabled',
        'ai_applications_review_enabled', 'ai_broadcast_assist_enabled',
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
                $values[$key] = in_array($key, self::BOOLEAN_FIELDS, true)
                    ? Setting::get($key) === '1'
                    : Setting::get($key);
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
            $rules[$key] = match (true) {
                in_array($key, self::BOOLEAN_FIELDS, true) => ['boolean'],
                in_array($key, self::URL_FIELDS, true) => ['nullable', 'string', 'max:2000', 'url:http,https'],
                in_array($key, self::LONG_TEXT_FIELDS, true) => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        $data = $request->validate($rules, [], [
            'telegram_bot_url' => 'посилання на бота',
            'telegram_webhook_url' => 'webhook URL',
            'discord_redirect_uri' => 'Redirect URI',
        ]);

        foreach (self::FIELDS[$group] as $key) {
            $value = in_array($key, self::BOOLEAN_FIELDS, true)
                ? (($data[$key] ?? false) ? '1' : null)
                : ($data[$key] ?? null);

            Setting::set($key, $value, $group);
        }

        return back()->with('status', 'settings-updated');
    }
}
