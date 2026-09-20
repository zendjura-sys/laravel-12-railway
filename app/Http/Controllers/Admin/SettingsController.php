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
        // Самі токени/ключі (секрети) звідси винесено в окрему вкладку
        // 'api_keys' нижче — тут лишається тільки не-секретна конфігурація
        // кожної інтеграції.
        'telegram' => ['telegram_bot_username', 'telegram_webhook_url', 'telegram_bot_url', 'telegram_group_id'],
        'discord' => ['discord_client_id', 'discord_redirect_uri', 'discord_guild_id'],
        // Усі токени й ключі сайту в одному місці — замість того, щоб
        // шукати кожен у своїй тематичній вкладці.
        'api_keys' => [
            'telegram_bot_token',
            'discord_client_secret',
            'discord_bot_token',
            'mistral_api_key',
            'mistral_proxy_url',
        ],
        'ai' => [
            'ai_reports_analysis_enabled',
            'ai_rejection_advice_enabled',
            'ai_grade_advice_enabled',
            'ai_applications_review_enabled',
            'ai_broadcast_assist_enabled',
            'ai_event_draft_enabled',
        ],
        'ai_prompts' => [
            'ai_reports_analysis_instructions',
            'ai_rejection_advice_instructions',
            'ai_grade_advice_instructions',
            'ai_applications_review_instructions',
            'ai_broadcast_assist_instructions',
            'ai_event_draft_instructions',
        ],
        // Пам'ятки "як правильно оформити звіт" — учасник бачить їх
        // кнопкою "Як оформити?" у формі подачі звіту, за типом. Не має
        // нічого спільного з AI-інструкціями вище: це готовий текст для
        // людини, а не промпт для моделі.
        'report_guides' => [
            'report_guide_bizwar',
            'report_guide_contract',
            'report_guide_investment',
            'report_guide_other',
        ],
        // Мобільний застосунок (Flutter): та сама Setting::get()/set(), без
        // окремої моделі чи таблиці — читається публічним GET /api/app-config
        // (без авторизації, застосунок має побачити maintenance-режим ще ДО
        // логіну) і кешується так само, як решта Setting-значень.
        'mobile_app' => [
            'mobile_app_enabled',
            'mobile_app_maintenance_message',
            'mobile_app_min_build',
            'mobile_app_latest_build',
            'mobile_app_update_message',
            'mobile_app_bank_enabled',
            'mobile_app_leaderboard_enabled',
            'mobile_app_reports_enabled',
            'mobile_app_download_url',
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
        'ai_applications_review_instructions', 'ai_broadcast_assist_instructions', 'ai_event_draft_instructions',
        'report_guide_bizwar', 'report_guide_contract', 'report_guide_investment', 'report_guide_other',
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
        'ai_applications_review_enabled', 'ai_broadcast_assist_enabled', 'ai_event_draft_enabled',
        'mobile_app_enabled', 'mobile_app_bank_enabled', 'mobile_app_leaderboard_enabled', 'mobile_app_reports_enabled',
    ];

    /**
     * Підмножина BOOLEAN_FIELDS, де незаданий стан (адмін ще жодного разу
     * не зберігав цю вкладку) означає "увімкнено", а не "вимкнено" — на
     * відміну від решти тумблерів (AI-фічі, опційні за задумом). Інакше
     * свіжий деплой без жодного дотику до цих налаштувань одразу вимкнув
     * би застосунок і його вкладки всім. Зберігаються явним '1'/'0'
     * (ніколи null), щоб відрізнити "не займали" від "явно вимкнули".
     *
     * @var array<int, string>
     */
    private const DEFAULT_ENABLED_BOOLEAN_FIELDS = [
        'mobile_app_enabled', 'mobile_app_bank_enabled', 'mobile_app_leaderboard_enabled', 'mobile_app_reports_enabled',
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
        'telegram_webhook_url', 'telegram_bot_url', 'discord_redirect_uri', 'mobile_app_download_url',
    ];

    public function index(): Response
    {
        $values = [];
        foreach (self::FIELDS as $group => $keys) {
            foreach ($keys as $key) {
                $values[$key] = match (true) {
                    in_array($key, self::DEFAULT_ENABLED_BOOLEAN_FIELDS, true) => Setting::get($key) !== '0',
                    in_array($key, self::BOOLEAN_FIELDS, true) => Setting::get($key) === '1',
                    default => Setting::get($key),
                };
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
                in_array($key, ['mobile_app_min_build', 'mobile_app_latest_build'], true) => ['nullable', 'integer', 'min:1'],
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
            $value = match (true) {
                in_array($key, self::DEFAULT_ENABLED_BOOLEAN_FIELDS, true) => ($data[$key] ?? false) ? '1' : '0',
                in_array($key, self::BOOLEAN_FIELDS, true) => ($data[$key] ?? false) ? '1' : null,
                default => $data[$key] ?? null,
            };

            Setting::set($key, $value, $group);
        }

        return back()->with('status', 'settings-updated');
    }
}
