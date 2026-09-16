<?php

namespace Addons\TelegramBot\Http\Controllers;

use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Публічний, БЕЗ auth/CSRF — сюди стукає лише сервер Telegram. Захист
 * подвійний: непередбачуваний секрет прямо в URL (основна перевірка —
 * працює навіть якщо якийсь проксі десь по дорозі зріже заголовки) плюс
 * той самий секрет, який Telegram повертає в заголовку
 * X-Telegram-Bot-Api-Secret-Token після setWebhook(..., secret_token).
 */
class WebhookController
{
    public function __invoke(Request $request, TelegramClient $telegram, string $secret): Response
    {
        $expected = Setting::get('telegram_webhook_secret');
        if (! $expected || ! hash_equals($expected, $secret)) {
            return response('forbidden', 403);
        }

        $message = $request->input('message');
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if (! $chatId || $text === '') {
            return response('ok');
        }

        $username = $message['from']['username'] ?? null;

        if (str_starts_with($text, '/start')) {
            $this->handleStart($telegram, (string) $chatId, $username, trim(substr($text, 6)));
        } elseif (str_starts_with($text, '/link')) {
            $this->handleStart($telegram, (string) $chatId, $username, trim(substr($text, 5)));
        } elseif (str_starts_with($text, '/status')) {
            $this->handleStatus($telegram, (string) $chatId);
        } else {
            $this->handleHelp($telegram, (string) $chatId);
        }

        return response('ok');
    }

    private function handleStart(TelegramClient $telegram, string $chatId, ?string $username, string $code): void
    {
        if ($code === '') {
            $telegram->sendMessage($chatId, "Вітаю в Monsory Family! 👋\n\nЩоб привʼязати цей чат до свого акаунту на сайті — зайдіть у Профіль → Telegram і натисніть «Згенерувати код», а потім надішліть його сюди командою:\n<code>/link КОД</code>");

            return;
        }

        $link = TelegramLink::findByValidCode($code);
        if (! $link) {
            $telegram->sendMessage($chatId, 'Код недійсний або вже прострочений (діє 10 хвилин). Згенеруйте новий на сайті.');

            return;
        }

        // Один Telegram-чат — один акаунт: якщо цей chat_id вже прив'язаний
        // до ІНШОГО користувача раніше, відв'язуємо той старий запис.
        TelegramLink::query()->where('chat_id', $chatId)->where('id', '!=', $link->id)->update(['chat_id' => null, 'linked_at' => null]);

        $link->update([
            'chat_id' => $chatId,
            'telegram_username' => $username,
            'linked_at' => now(),
            'link_code' => null,
        ]);

        $telegram->sendMessage($chatId, "Готово! Цей чат привʼязано до акаунту <b>{$link->user->name}</b> на сайті Monsory Connect.\n\nНадішліть /status, щоб перевірити звʼязок.");
    }

    private function handleStatus(TelegramClient $telegram, string $chatId): void
    {
        $link = TelegramLink::query()->where('chat_id', $chatId)->whereNotNull('linked_at')->first();

        if (! $link) {
            $telegram->sendMessage($chatId, 'Цей чат ще не привʼязаний до акаунту. Скористайтесь /start.');

            return;
        }

        $telegram->sendMessage($chatId, "Привʼязано до акаунту <b>{$link->user->name}</b> ({$link->user->email}).");
    }

    private function handleHelp(TelegramClient $telegram, string $chatId): void
    {
        $telegram->sendMessage($chatId, "Доступні команди:\n/start — почати\n/link КОД — привʼязати акаунт\n/status — перевірити звʼязок");
    }
}
