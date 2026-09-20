<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\MemberCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Картка учасника" на сторінці "Банк" — стабільний 16-значний номер без нового стовпця в users. */
class MemberCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_number_is_always_sixteen_digits(): void
    {
        $user = User::factory()->create();

        $this->assertSame(16, strlen(MemberCard::number($user)));
        $this->assertMatchesRegularExpression('/^\d{16}$/', MemberCard::number($user));
    }

    public function test_the_number_is_stable_across_calls(): void
    {
        $user = User::factory()->create();

        $this->assertSame(MemberCard::number($user), MemberCard::number($user));
    }

    public function test_different_users_get_different_numbers(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertNotSame(MemberCard::number($a), MemberCard::number($b));
    }

    public function test_the_masked_number_hides_everything_but_the_last_four_digits(): void
    {
        $user = User::factory()->create();

        $masked = MemberCard::masked($user);
        $full = MemberCard::number($user);

        $this->assertSame(16, strlen($masked));
        $this->assertSame(str_repeat('*', 12), substr($masked, 0, 12));
        $this->assertSame(substr($full, -4), substr($masked, -4));
    }
}
