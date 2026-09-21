<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

/**
 * Push-сповіщення в мобільний застосунок (FCM) — четвертий канал
 * доставки поруч із web-копією, Telegram і браузерним push (див.
 * Addons\Notifications\Services\NotificationService).
 *
 * Мовчить, поки не завантажено службовий обліковий запис Firebase
 * (config('firebase.credentials')) — так само, як WebPushSender без
 * VAPID-ключів чи TelegramClient без токена: середовище без push
 * просто не доставляє цей канал, без падінь.
 */
class MobilePushSender
{
    public function isConfigured(): bool
    {
        $path = config('firebase.credentials');

        return filled($path) && is_string($path) && is_file($path);
    }

    public function sendToUser(User $user, string $title, ?string $body = null, ?string $url = null): void
    {
        $this->sendToUserIds([$user->id], $title, $body, $url);
    }

    /**
     * Масова розсилка (Broadcasts) — один запит на всі токени отримувачів
     * замість sendToUser() у циклі, і чанками по 500 (ліміт multicast у FCM).
     *
     * @param  iterable<int>  $userIds
     */
    public function sendToUserIds(iterable $userIds, string $title, ?string $body = null, ?string $url = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::query()->whereIn('user_id', [...$userIds])->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $messaging = (new Factory())->withServiceAccount(config('firebase.credentials'))->createMessaging();
        } catch (Throwable $e) {
            Log::warning('mobile_push.factory_failed', ['message' => $e->getMessage()]);

            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body ?? ''))
            ->withData(['url' => $url ?: '/notifications']);

        $deadTokens = [];

        foreach ($tokens->chunk(500) as $chunk) {
            try {
                /** @var MulticastSendReport $report */
                $report = $messaging->sendMulticast($message, $chunk->all());
            } catch (Throwable $e) {
                Log::warning('mobile_push.send_failed', ['message' => $e->getMessage()]);

                continue;
            }

            if ($report->hasFailures()) {
                array_push($deadTokens, ...$report->invalidTokens(), ...$report->unknownTokens());
            }
        }

        // Токен, який Android/Firebase вже вважає недійсним (переустановка,
        // вихід з акаунту, минув строк) — назавжди лишиться "мертвим" без
        // чищення тут, і кожна наступна відправка накопичувала б помилки.
        if ($deadTokens !== []) {
            DeviceToken::query()->whereIn('token', $deadTokens)->delete();
        }
    }
}
