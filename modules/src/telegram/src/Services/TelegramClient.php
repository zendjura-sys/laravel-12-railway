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

    /**
     * Одноразовая ссылка-приглашение в группу.
     *
     * Bot API НЕ умеет добавлять человека в чат — такого метода нет
     * вовсе, это доступно только клиентскому API от имени самого
     * пользователя. Максимум, что может бот, — выдать ссылку, по которой
     * человек входит сам в одно нажатие. Поэтому лимит в одного участника
     * и срок жизни: ссылка не должна гулять дальше того, кому выдана.
     *
     * Требует, чтобы бот был администратором группы с правом приглашать.
     *
     * @return array{ok:bool,link:?string,message:string}
     */
    public function createInviteLink(string $chatId, int $ttlHours = 48): array
    {
        $response = $this->call('createChatInviteLink', [
            'chat_id' => $chatId,
            'name' => 'Monsory · заявка',
            'member_limit' => 1,
            'expire_date' => now()->addHours($ttlHours)->timestamp,
        ]);

        return [
            'ok' => ($response['ok'] ?? false) === true,
            'link' => $response['result']['invite_link'] ?? null,
            'message' => $response['description'] ?? 'Немає відповіді від Telegram.',
        ];
    }

    /**
     * Пропустить заявку на вступление, если группа закрыта ссылкой с
     * подтверждением (creates_join_request). Это единственный способ,
     * которым бот может впустить человека без действий администратора —
     * но и он требует, чтобы человек сам нажал на ссылку.
     */
    public function approveJoinRequest(string $chatId, int $userId): bool
    {
        return ($this->call('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ])['ok'] ?? false) === true;
    }

    public function declineJoinRequest(string $chatId, int $userId): bool
    {
        return ($this->call('declineChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ])['ok'] ?? false) === true;
    }

    /**
     * Статус человека в группе: creator/administrator/member/restricted/
     * left/kicked. null — спросить не удалось.
     */
    public function chatMemberStatus(string $chatId, int $userId): ?string
    {
        $response = $this->call('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);

        return $response['result']['status'] ?? null;
    }

    /** @return array{ok:bool,title:?string,message:string} */
    public function chatInfo(string $chatId): array
    {
        $response = $this->call('getChat', ['chat_id' => $chatId]);

        return [
            'ok' => ($response['ok'] ?? false) === true,
            'title' => $response['result']['title'] ?? null,
            'message' => $response['description'] ?? 'Немає відповіді від Telegram.',
        ];
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
            // chat_join_request — чтобы бот сам впускал в группу тех, чью
            // заявку уже одобрили, без участия администратора.
            'allowed_updates' => json_encode(['message', 'callback_query', 'chat_join_request']),
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
