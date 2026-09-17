<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GuideContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Не перевіряємо ->component() — його файлова перевірка шукає
     * resources/js/pages (нижній регістр) замість Pages, який тут
     * використовується всюди, і тому валиться на будь-якій сторінці
     * проєкту, не лише на цій.
     */
    public function test_a_member_can_view_the_guide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('guide'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', GuideContent::categories())
            );
    }

    /** Доступна ще до підтвердження email — саме тоді найбільше питань. */
    public function test_an_unverified_member_can_view_the_guide(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('guide'))->assertOk();
    }

    public function test_a_guest_cannot_view_the_guide(): void
    {
        $this->get(route('guide'))->assertRedirect(route('login'));
    }
}
