<?php

namespace Addons\Notifications\Services;

use Addons\Notifications\Models\Notification;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
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
    public function notify(User $user, string $type, string $title, ?string $body = null): Notification
    {
        $notification = Notification::notify($user->id, $type, $title, $body);

        $this->deliverToTelegram($user, $title, $body);

        return $notification;
    }

    private function deliverToTelegram(User $user, string $title, ?string $body): void
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

        $text = '<b>'.e($title).'</b>'.($body ? "\n\n".e($body) : '');
        $client->sendMessage((string) $link->chat_id, $text);
    }
}
