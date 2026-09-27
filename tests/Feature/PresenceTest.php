<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_web_request_marks_user_online(): void
    {
        $user = User::factory()->create();
        $this->assertFalse(Presence::isOnline($user));

        $this->actingAs($user)->post('/presence/ping')->assertNoContent();

        $this->assertTrue(Presence::isOnline($user->fresh()));
    }

    public function test_api_ping_marks_token_user_online(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/presence/ping')->assertNoContent();

        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_guest_request_does_not_fail(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_touch_is_throttled(): void
    {
        $user = User::factory()->create();
        Carbon::setTestNow('2026-09-22 12:00:00');
        Presence::touch($user);
        Carbon::setTestNow('2026-09-22 12:00:10');
        Presence::touch($user->fresh());

        $this->assertSame('2026-09-22 12:00:00', $user->fresh()->last_seen_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_labels(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $user = User::factory()->make(['gender' => 'f']);

        $user->last_seen_at = now()->subSeconds(30);
        $this->assertSame('у мережі', Presence::label($user));
        $this->assertSame('🟢', Presence::payload($user)['emoji']);

        $user->last_seen_at = now()->subMinutes(7);
        $this->assertSame('була у мережі 7 хв тому', Presence::label($user));
        $this->assertSame('🔴', Presence::payload($user)['emoji']);

        // 12:00 UTC = 15:00 Київ; 3 год тому — сьогодні о 12:00 за Києвом.
        $user->last_seen_at = now()->subHours(3);
        $this->assertSame('була у мережі сьогодні о 12:00', Presence::label($user));

        $user->gender = null;
        $user->last_seen_at = now()->subDay();
        $this->assertSame('був у мережі вчора о 15:00', Presence::label($user));

        $user->last_seen_at = null;
        $this->assertSame('не в мережі', Presence::label($user));
        Carbon::setTestNow();
    }
}
