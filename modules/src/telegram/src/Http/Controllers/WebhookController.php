<?php

namespace Addons\TelegramBot\Http\Controllers;

use Addons\TelegramBot\Bot\BotHandler;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Публичный, БЕЗ auth/CSRF — сюда стучит только сервер Telegram. Защита
 * двойная: непредсказуемый секрет прямо в URL (основная проверка —
 * работает, даже если какой-то прокси по дороге срежет заголовки) плюс
 * тот же секрет, который Telegram возвращает в заголовке
 * X-Telegram-Bot-Api-Secret-Token после setWebhook(..., secret_token).
 */
class WebhookController
{
    public function __invoke(Request $request, BotHandler $bot, string $secret): Response
    {
        $expected = Setting::get('telegram_webhook_secret');
        if (! $expected || ! hash_equals($expected, $secret)) {
            return response('forbidden', 403);
        }

        try {
            $bot->handle($request->all());
        } catch (\Throwable $e) {
            // Ответить Telegram надо в любом случае: на не-200 он повторяет
            // апдейт по нарастающей, и одна ошибка в обработчике
            // превращается в шквал одинаковых нажатий.
            Log::error('telegram: помилка обробки апдейту', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return response('ok');
    }
}
