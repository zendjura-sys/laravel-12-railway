<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ручне завантаження .apk у адмінці — резервний шлях на випадок, коли
 * deploy/setup-vps.sh не може сам дотягнутись до GitHub Release з VDS.
 */
class MobileApkUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $targetPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->targetPath = public_path('downloads/monsory-connect.apk');
    }

    protected function tearDown(): void
    {
        File::delete($this->targetPath);
        parent::tearDown();
    }

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_upload_an_apk_and_it_lands_in_public_downloads(): void
    {
        $file = UploadedFile::fake()->create('monsory-connect.apk', 2048);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.mobile-app.apk'), ['apk' => $file])
            ->assertRedirect();

        $this->assertFileExists($this->targetPath);
        $this->assertSame('/downloads/monsory-connect.apk', Setting::get('mobile_app_download_url'));
    }

    public function test_a_non_apk_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('not-an-apk.zip', 100);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.mobile-app.apk'), ['apk' => $file])
            ->assertSessionHasErrors('apk');

        $this->assertFileDoesNotExist($this->targetPath);
    }

    public function test_a_member_without_permission_cannot_upload(): void
    {
        $file = UploadedFile::fake()->create('monsory-connect.apk', 2048);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.settings.mobile-app.apk'), ['apk' => $file])
            ->assertForbidden();

        $this->assertFileDoesNotExist($this->targetPath);
    }

    /**
     * deploy/setup-vps.sh і кнопка завантаження вище самі пишуть сюди
     * відносний шлях — звичайна форма налаштувань має його приймати
     * так само, а не тільки повний https://... URL.
     */
    public function test_settings_form_accepts_a_relative_download_url(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update', 'mobile_app'), [
                'mobile_app_download_url' => '/downloads/monsory-connect.apk',
            ])
            ->assertSessionDoesntHaveErrors('mobile_app_download_url');

        $this->assertSame('/downloads/monsory-connect.apk', Setting::get('mobile_app_download_url'));
    }

    public function test_settings_form_rejects_a_download_url_without_scheme_or_leading_slash(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update', 'mobile_app'), [
                'mobile_app_download_url' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('mobile_app_download_url');
    }
}
