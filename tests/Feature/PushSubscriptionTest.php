<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Реєстрація/зняття браузерної підписки на push. Саму відправку
 * (App\Support\WebPushSender) тут не перевіряємо — реальний виклик
 * до push-сервісу за межами тестового середовища; тестуємо лише
 * зберігання/оновлення/видалення підписки, яке керує цим каналом.
 */
class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-token',
            ],
        ];
    }

    public function test_a_member_can_register_a_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push-subscriptions.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.subscribed', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/abc123'),
        ]);
    }

    public function test_registering_the_same_endpoint_twice_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->payload());
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->payload());

        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());
    }

    public function test_validation_requires_endpoint_and_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push-subscriptions.store'), [])
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_a_member_can_remove_their_subscription(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->payload());

        $this->actingAs($user)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
            ->assertOk()
            ->assertJsonPath('data.subscribed', false);

        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $user->id]);
    }

    public function test_a_member_cannot_remove_another_members_subscription(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->actingAs($owner)->postJson(route('push-subscriptions.store'), $this->payload());

        $this->actingAs($intruder)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $owner->id]);
    }

    public function test_a_guest_cannot_register_a_subscription(): void
    {
        $this->postJson(route('push-subscriptions.store'), $this->payload())->assertUnauthorized();
    }
}
