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

        $data = $request->validate(
            array_fill_keys(self::FIELDS[$group], ['nullable', 'string', 'max:2000']),
        );

        foreach (self::FIELDS[$group] as $key) {
            Setting::set($key, $data[$key] ?? null, $group);
        }

        return back()->with('status', 'settings-updated');
    }
}
