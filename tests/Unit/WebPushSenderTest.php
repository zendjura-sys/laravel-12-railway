<?php

namespace Tests\Unit;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * isConfigured() — та сама "тиха відмова", що й у TelegramClient: без
 * VAPID-ключів канал просто нічого не робить, а не кидає помилку.
 */
class WebPushSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_not_configured_without_vapid_keys(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

        $this->assertFalse((new WebPushSender())->isConfigured());
    }

    public function test_is_configured_with_both_vapid_keys(): void
    {
        config(['webpush.vapid.public_key' => 'pub', 'webpush.vapid.private_key' => 'priv']);

        $this->assertTrue((new WebPushSender())->isConfigured());
    }

    public function test_sending_without_vapid_keys_does_not_touch_subscriptions(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/push/1',
            'endpoint_hash' => hash('sha256', 'https://example.com/push/1'),
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
        ]);

        (new WebPushSender())->sendToUser($user, 'Тест');

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id]);
    }
}
