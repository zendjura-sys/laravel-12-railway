<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnreadNotificationsBadgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * У тестовій БД мігрують лише core-таблиці — Notifications (модуль) тут
     * не встановлений, тож "notifications" таблиці взагалі не існує. Це
     * саме той сценарій, який має не ламати рендер жодної сторінки:
     * Schema::hasTable() у middleware має тихо повернути 0.
     */
    public function test_unread_notifications_prop_defaults_to_zero_without_the_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 0));
    }

    public function test_guest_gets_zero_unread_notifications(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 0));
    }
}
