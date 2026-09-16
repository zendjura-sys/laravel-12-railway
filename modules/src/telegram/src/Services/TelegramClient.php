<?php

namespace Addons\TelegramBot\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкая обёртка над Telegram Bot API. Токен берётся из настроек Telegram
 * в админке (Setting::get) — отдельного поля тут не заводим.
 *
 * Навигация в боте построена на РЕДАКТИРОВАНИИ одного сообщения
 * (editMessageText), а не на отправке нового на каждое нажатие. Иначе чат
 * за пять переходов превращается в простыню из брошенных меню, где
 * пользователь тыкает в устаревшие кнопки выше по истории.
 */
class TelegramClient
{
    private ?string $token;

    public function __construct()
    {
        $this->token = Setting::get('telegram_bot_token') ?: null;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->token;
    }

    /**
     * @param  array<string,mixed>|null  $keyboard  готовый reply_markup
     * @return int|null  message_id отправленного сообщения
     */
    public function sendMessage(string $chatId, string $text, ?array $keyboard = null): ?int
    {
        $response = $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => $keyboard ? json_encode($keyboard) : null,
        ], static fn ($v) => $v !== null));

        return $response['result']['message_id'] ?? null;
    }

    /**
     * @param  array<string,mixed>|null  $keyboard
     * @return bool  false, если сообщение отредактировать не удалось
     */
    public function editMessage(string $chatId, int $messageId, string $text, ?array $keyboard = null): bool
    {
        $response = $this->call('editMessageText', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => $keyboard ? json_encode($keyboard) : null,
        ], static fn ($v) => $v !== null));

        return ($response['ok'] ?? false) === true;
    }

    /**
     * Убирает "часики" на нажатой кнопке. Telegram крутит их до 30 секунд,
     * и без этого вызова любое нажатие выглядит как зависшее.
     */
    public function answerCallback(string $callbackId, ?string $text = null): void
    {
        $this->call('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackId,
            'text' => $text,
        ], static fn ($v) => $v !== null));
    }

    public function deleteMessage(string $chatId, int $messageId): void
    {
        $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function setWebhook(string $url, string $secret): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Спочатку вкажіть Bot Token у Налаштуваннях → Telegram.'];
        }

        $response = $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            // callback_query обязателен: без него Telegram не присылает
            // нажатия inline-кнопок, и всё меню бота выглядит мёртвым.
            'allowed_updates' => json_encode(['message', 'callback_query']),
            'drop_pending_updates' => true,
        ]);

        return [
            'ok' => ($response['ok'] ?? false) === true,
            'message' => $response['description'] ?? 'Немає відповіді від Telegram.',
        ];
    }

    /** @return array{ok:bool,message:string} */
    public function deleteWebhook(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bot Token не вказано.'];
        }

        $response = $this->call('deleteWebhook', []);

        return [
            'ok' => ($response['ok'] ?? false) === true,
            'message' => $response['description'] ?? 'Немає відповіді від Telegram.',
        ];
    }

    /** @return array<string,mixed>|null */
    public function getWebhookInfo(): ?array
    {
        $result = $this->call('getWebhookInfo', [])['result'] ?? null;

        // Строго массив: при неверном токене или ответе неожиданной формы
        // сюда приходит true/строка, и без этой проверки страница админки
        // падает целиком из-за возвращаемого типа.
        return is_array($result) ? $result : null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.telegram.api_url', 'https://api.telegram.org'), '/');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function call(string $method, array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'description' => 'Bot Token не вказано.'];
        }

        try {
            // Вебхук должен ответить Telegram быстро, иначе апдейт придёт
            // повторно и пользователь увидит меню дважды. Поэтому жёсткий
            // таймаут и никаких ретраев внутри обработки нажатия.
            $response = Http::timeout(8)
                ->asForm()
                ->post($this->baseUrl()."/bot{$this->token}/{$method}", $payload);

            $json = $response->json();

            if (! is_array($json)) {
                return ['ok' => false, 'description' => 'Некоректна відповідь Telegram.'];
            }

            if (($json['ok'] ?? false) !== true) {
                Log::warning('telegram: '.$method.' failed', ['description' => $json['description'] ?? null]);
            }

            return $json;
        } catch (\Throwable $e) {
            Log::warning('telegram: '.$method.' threw', ['error' => $e->getMessage()]);

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }
}
