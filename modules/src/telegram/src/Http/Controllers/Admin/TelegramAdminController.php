<?php

namespace Addons\TelegramBot\Http\Controllers\Admin;

use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAdminController
{
    public function index(TelegramClient $telegram): Response
    {
        return Inertia::render('Admin/Telegram/Index', [
            'configured' => $telegram->isConfigured(),
            'botUsername' => Setting::get('telegram_bot_username'),
            'webhookInfo' => $telegram->getWebhookInfo(),
            'linkedCount' => TelegramLink::query()->whereNotNull('linked_at')->count(),
        ]);
    }

    public function setupWebhook(Request $request, TelegramClient $telegram): JsonResponse
    {
        $secret = Str::random(32);
        $url = route('telegram.webhook', ['secret' => $secret]);

        $result = $telegram->setWebhook($url, $secret);

        if ($result['ok']) {
            Setting::set('telegram_webhook_secret', $secret, 'telegram');
        }

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Webhook встановлено.' : ('Помилка: '.$result['message']),
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function removeWebhook(TelegramClient $telegram): JsonResponse
    {
        $result = $telegram->deleteWebhook();
        Setting::set('telegram_webhook_secret', null, 'telegram');

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Webhook знято.' : ('Помилка: '.$result['message']),
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
