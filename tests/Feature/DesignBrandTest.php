<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignBrandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(SystemPermissionsSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_png_logo_is_stored_and_served(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.design.brand'), [
            'logo' => UploadedFile::fake()->image('logo.png', 320, 120),
        ])->assertSessionHasNoErrors();

        $path = Setting::get('design_logo');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull(app(\App\Support\DesignSettings::class)::assetUrl('design_logo'));
    }

    /**
     * Форма предлагает .ico, и он обязан проходить. Раньше правило image
     * стояло рядом с mimes:ico, а image знает только jpg/jpeg/png/gif/
     * bmp/webp — то есть ни один .ico не проходил в принципе.
     */
    public function test_ico_favicon_is_accepted(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.design.brand'), [
            'favicon' => UploadedFile::fake()->create('favicon.ico', 8, 'image/vnd.microsoft.icon'),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(Setting::get('design_favicon'));
    }

    /** Логотип вставляется на каждую страницу — SVG туда пускать нельзя. */
    public function test_svg_logo_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.design.brand'), [
            'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull(Setting::get('design_logo'));
    }

    public function test_renamed_script_is_refused_as_favicon(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.design.brand'), [
            'favicon' => UploadedFile::fake()->create('evil.ico', 4, 'application/x-php'),
        ])->assertSessionHasErrors('favicon');
    }

    /** Замена не должна копить в storage все когда-либо загруженные файлы. */
    public function test_replacing_the_logo_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.design.brand'), [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);
        $first = Setting::get('design_logo');

        $this->actingAs($admin)->post(route('admin.design.brand'), [
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);
        $second = Setting::get('design_logo');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_plain_member_cannot_change_the_brand(): void
    {
        $this->seed(SystemPermissionsSeeder::class);
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($member)
            ->post(route('admin.design.brand'), ['siteName' => 'Захоплено'])
            ->assertForbidden();
    }
}
