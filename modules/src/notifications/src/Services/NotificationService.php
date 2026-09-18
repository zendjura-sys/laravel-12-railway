<?php

namespace Addons\Notifications\Services;

use Addons\Notifications\Models\Notification;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use Addons\TelegramBot\Support\MessageFormat;
use App\Models\User;

/**
 * Єдина точка входу для персональних сповіщень: завжди пише web-копію
 * (Notification), і додатково дублює в Telegram, якщо TelegramBot
 * встановлено й активовано, а сам отримувач прив'язав акаунт.
 *
 * Клас TelegramBot-модуля береться через class_exists() — з тими ж
 * самими причинами, що й рядкові літерали в routes/events.php: notifications
 * не повинен падати, якщо TelegramBot не встановлено чи не активовано.
 */
class NotificationService
{
    /** Емодзі заголовка в Telegram за типом сповіщення — суто візуальне, ні на що інше не впливає. */
    private const TYPE_EMOJI = [
        'report_reviewed' => '📋',
        'report_created' => '📥',
        'leave_request_reviewed' => '🏖',
        'achievement_unlocked' => '🏆',
        'investment_tier_unlocked' => '💎',
    ];

    /**
     * @param  array{text:string,url:string}|null  $telegramButton  Кнопка-посилання
     *         під повідомленням у Telegram (напр. "Список заявок" на
     *         відповідну сторінку адмінки) — на web-копію сповіщення не
     *         впливає, там і так є свій список.
     */
    public function notify(User $user, string $type, string $title, ?string $body = null, ?array $telegramButton = null): Notification
    {
        $notification = Notification::notify($user->id, $type, $title, $body);

        $this->deliverToTelegram($user, $type, $title, $body, $telegramButton);

        return $notification;
    }

    /** @param array{text:string,url:string}|null $telegramButton */
    private function deliverToTelegram(User $user, string $type, string $title, ?string $body, ?array $telegramButton): void
    {
        if (! class_exists(TelegramLink::class) || ! class_exists(TelegramClient::class)) {
            return;
        }

        $link = TelegramLink::query()->where('user_id', $user->id)->whereNotNull('linked_at')->first();
        if (! $link || ! $link->chat_id) {
            return;
        }

        $client = new TelegramClient();
        if (! $client->isConfigured()) {
            return;
        }

        $emoji = self::TYPE_EMOJI[$type] ?? '🔔';
        $text = MessageFormat::card($emoji, $title, $body ? e($body) : null);
        $keyboard = $telegramButton ? ['inline_keyboard' => [[$telegramButton]]] : null;
        $client->sendMessage((string) $link->chat_id, $text, $keyboard);
    }
}
