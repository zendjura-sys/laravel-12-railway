<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Push-сповіщення у браузер — третій канал доставки поруч із web-копією й
 * Telegram (див. Addons\Notifications\Services\NotificationService).
 *
 * Мовчки нічого не робить, поки VAPID-ключі не задані в .env: так само,
 * як TelegramClient::isConfigured() — середовище без налаштованого push
 * не повинно падати, лише не доставляти цей канал.
 */
class WebPushSender
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public function sendToUser(User $user, string $title, ?string $body = null, ?string $url = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = PushSubscription::query()->where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?: '/notifications',
        ]);

        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => [
                            'p256dh' => $subscription->public_key,
                            'auth' => $subscription->auth_token,
                        ],
                    ]),
                    $payload,
                );
            } catch (Throwable $e) {
                Log::warning('webpush.queue_failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
            }
        }

        // Підписка, яку браузер чи провайдер уже відкликав (410 Gone /
        // 404), назавжди лишиться "мертвою" — без чищення тут вона б
        // накопичувалась і сповільнювала кожну наступну відправку.
        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('endpoint', $report->getEndpoint())
                    ->delete();
            }
        }
    }
}
