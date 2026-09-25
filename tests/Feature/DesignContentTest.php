<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FamilyContent;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(SystemPermissionsSeeder::class);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    /** @return array<int,array<string,mixed>> */
    private function payloadFrom(array $positions): array
    {
        return [
            'leadership' => [],
            'positions' => $positions,
            'directions' => [],
            'promotionCriteria' => [],
            'about' => [],
        ];
    }

    /**
     * Головний сценарій, заради якого зроблено ключ замість індексу:
     * адмін переставляє посади місцями через звичайну форму (Дизайн →
     * Розділи), а вже призначені посади в людей лишаються правильними.
     */
    public function test_reordering_positions_in_the_admin_form_keeps_assignments_correct(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['position_key' => 'director']);

        $positions = FamilyContent::positions();
        $director = array_pop($positions);
        array_unshift($positions, $director);

        $this->actingAs($admin)
            ->put(route('admin.design.content'), $this->payloadFrom($positions))
            ->assertSessionHasNoErrors();

        $this->assertSame('Директор', $member->fresh()->position_title);
    }

    /** Видалення призначеної посади — чесне попередження, а не тиша. */
    public function test_removing_an_assigned_position_warns_the_admin(): void
    {
        $admin = $this->admin();
        User::factory()->create(['position_key' => 'director']);

        $positions = array_values(array_filter(
            FamilyContent::positions(),
            fn (array $p) => $p['key'] !== 'director',
        ));

        $response = $this->actingAs($admin)->put(route('admin.design.content'), $this->payloadFrom($positions));

        $response->assertSessionHasNoErrors();
        $this->assertStringContainsString('без призначеної посади', session('status'));
    }

    /** Видалення посади, якою ніхто не користується, — звичайне «Зміст збережено». */
    public function test_removing_an_unassigned_position_does_not_warn(): void
    {
        $admin = $this->admin();

        $positions = array_values(array_filter(
            FamilyContent::positions(),
            fn (array $p) => $p['key'] !== 'director',
        ));

        $response = $this->actingAs($admin)->put(route('admin.design.content'), $this->payloadFrom($positions));

        $response->assertSessionHasNoErrors();
        $this->assertSame('Зміст збережено. Сайт і бот оновилися одночасно.', session('status'));
    }
}
