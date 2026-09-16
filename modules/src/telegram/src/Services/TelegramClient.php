<?php

namespace Addons\TelegramBot\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Тонка обгортка над Telegram Bot API. Токен береться з уже наявних
 * налаштувань Telegram в адмінці (Setting::get) — окремого поля для
 * нього тут не заводимо.
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

    public function sendMessage(string $chatId, string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $response = Http::asForm()->post($this->endpoint('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        return $response->successful() && ($response->json('ok') === true);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function setWebhook(string $url, string $secret): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Спочатку вкажіть Bot Token у Налаштуваннях → Telegram.'];
        }

        $response = Http::asForm()->post($this->endpoint('setWebhook'), [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message']),
        ]);

        return [
            'ok' => $response->successful() && $response->json('ok') === true,
            'message' => $response->json('description', $response->body()),
        ];
    }

    public function deleteWebhook(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bot Token не вказано.'];
        }

        $response = Http::asForm()->post($this->endpoint('deleteWebhook'));

        return [
            'ok' => $response->successful() && $response->json('ok') === true,
            'message' => $response->json('description', $response->body()),
        ];
    }

    /** @return array<string,mixed>|null */
    public function getWebhookInfo(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::get($this->endpoint('getWebhookInfo'));

        return $response->successful() ? $response->json('result') : null;
    }

    private function endpoint(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }
}
