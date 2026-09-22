<?php

namespace Addons\Notifications\Services;

use Addons\Notifications\Models\Notification;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use Addons\TelegramBot\Support\MessageFormat;
use App\Models\User;
use App\Support\MobilePushSender;
use App\Support\WebPushSender;

/**
 * Єдина точка входу для персональних сповіщень: завжди пише web-копію
 * (Notification), додатково дублює в Telegram, якщо TelegramBot
 * встановлено й активовано, а сам отримувач прив'язав акаунт, у
 * браузерний push, якщо в отримувача є активна підписка, і в мобільний
 * push (FCM), якщо на пристрої зареєстровано токен.
 *
 * Клас TelegramBot-модуля береться через class_exists() — з тими ж
 * самими причинами, що й рядкові літерали в routes/events.php: notifications
 * не повинен падати, якщо TelegramBot не встановлено чи не активовано.
 * WebPushSender і MobilePushSender — класи ядра, тому завжди доступні;
 * самі вони мовчать, якщо VAPID-ключі чи Firebase-обліковка не налаштовані.
 */
class NotificationService
{
    /** Емодзі заголовка в Telegram за типом сповіщення — суто візуальне, ні на що інше не впливає. */
    private const TYPE_EMOJI = [
        'report_reviewed' => '📋',
        'report_created' => '📥',
        'leave_request_created' => '📥',
        'leave_request_reviewed' => '🏖',
        'achievement_unlocked' => '🏆',
        'investment_tier_unlocked' => '💎',
        'member_warning_issued' => '⚠️',
    ];

    /**
     * @param  array{text:string,url:string}|null  $telegramButton  Кнопка-посилання
     *         під повідомленням у Telegram (напр. "Список заявок" на
     *         відповідну сторінку адмінки) — на web-копію сповіщення не
     *         впливає, там і так є свій список.
     */
    public function notify(User $user, string $type, string $title, ?string $body = null, ?array $telegramButton = null): Notification
    {
        // Та сама url, що йде під кнопкою в Telegram і в data-пейлоуд
        // push — тепер зберігається і у власній web-копії, щоб "дзвіночок"
        // у мобільному застосунку теж міг вести за призначенням, а не
        // лише позначати сповіщення прочитаним.
        $notification = Notification::notify($user->id, $type, $title, $body, $telegramButton['url'] ?? null);

        $this->deliverToTelegram($user, $type, $title, $body, $telegramButton);
        $this->deliverToWebPush($user, $title, $body, $telegramButton['url'] ?? null);
        $this->deliverToMobilePush($user, $title, $body, $telegramButton['url'] ?? null);

        return $notification;
    }

    private function deliverToWebPush(User $user, string $title, ?string $body, ?string $url): void
    {
        (new WebPushSender())->sendToUser($user, $title, $body, $url);
    }

    private function deliverToMobilePush(User $user, string $title, ?string $body, ?string $url): void
    {
        (new MobilePushSender())->sendToUser($user, $title, $body, $url);
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
        $messageId = $client->sendMessage((string) $link->chat_id, $text, $keyboard);

        // Той самий випадок, що й у SendBroadcastTelegramMessage: бот
        // заблокований чи чат видалено — знімаємо прив'язку одразу, а не
        // мовчки повторюємо ту саму невдачу на кожному наступному
        // особистому сповіщенні цього учасника.
        if ($messageId === null && $client->lastErrorIsPermanent()) {
            $link->update(['linked_at' => null, 'chat_id' => null]);
        }
    }
}
