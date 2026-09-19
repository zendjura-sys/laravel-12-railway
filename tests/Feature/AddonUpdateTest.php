<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use Database\Seeders\AddonPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AddonUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Файлы аддонов лежат в storage напрямую, а не через Storage-фасад,
     * поэтому RefreshDatabase их не трогает: убираем сами, иначе каталог
     * от прошлого прогона мешает следующему.
     */
    protected function tearDown(): void
    {
        foreach (['demo', 'other'] as $slug) {
            $dir = storage_path('app/addons/module/'.$slug);
            if (is_dir($dir)) {
                \Illuminate\Support\Facades\File::deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        // addons.manage выдаёт именно этот сидер, не системный.
        $this->seed(AddonPermissionsSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }

    /** Собирает минимальный валидный пакет-модуль указанной версии. */
    private function package(string $version, string $slug = 'demo'): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'addon').'.zip';

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode([
            'type' => 'module',
            'slug' => $slug,
            'name' => 'Demo',
            'version' => $version,
            'min_core_version' => '1.0.0',
        ]));
        $zip->addFromString('README.txt', "version {$version}");
        $zip->close();

        return new UploadedFile($zipPath, "(Modules)({$version})Demo.zip", 'application/zip', null, true);
    }

    private function upload(User $admin, UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->post(route('admin.addons.upload', 'module'), ['package' => $file]);
    }

    public function test_first_upload_installs_the_package(): void
    {
        $admin = $this->admin();

        $response = $this->upload($admin, $this->package('1.0.0'));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(1, Addon::where('slug', 'demo')->count());
        $this->assertSame('inactive', Addon::where('slug', 'demo')->value('status'));
    }

    /**
     * Главное: новая версия ОБНОВЛЯЕТ запись, а не заводит вторую рядом.
     * Из-за этого в списке аддонов копились дубликаты одного модуля.
     */
    public function test_a_newer_version_updates_in_place_instead_of_duplicating(): void
    {
        $admin = $this->admin();
        $this->upload($admin, $this->package('1.0.0'));

        $response = $this->upload($admin, $this->package('1.1.0'));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertStringContainsString('Оновлено з 1.0.0 до 1.1.0', $response->json('message'));

        $this->assertSame(1, Addon::where('slug', 'demo')->count(), 'дубліката бути не повинно');
        $this->assertSame('1.1.0', Addon::where('slug', 'demo')->value('version'));
    }

    /** Активный модуль после обновления остаётся активным — иначе сайт «моргает». */
    public function test_an_active_addon_stays_active_after_an_update(): void
    {
        $admin = $this->admin();
        $this->upload($admin, $this->package('1.0.0'));

        $addon = Addon::where('slug', 'demo')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.addons.activate', $addon))->assertOk();

        $this->upload($admin, $this->package('1.1.0'));

        $fresh = Addon::where('slug', 'demo')->firstOrFail();
        $this->assertSame('active', $fresh->status);
        $this->assertSame('1.1.0', $fresh->version);
        // Новая версия может принести новые миграции — флаг сбрасывается.
        $this->assertFalse((bool) $fresh->migrations_applied);
    }

    public function test_files_of_the_previous_version_are_removed(): void
    {
        $admin = $this->admin();
        $this->upload($admin, $this->package('1.0.0'));
        $oldPath = storage_path('app/'.Addon::where('slug', 'demo')->value('path'));
        $this->assertDirectoryExists($oldPath);

        $this->upload($admin, $this->package('1.1.0'));

        $this->assertDirectoryDoesNotExist($oldPath);
        $this->assertDirectoryExists(storage_path('app/'.Addon::where('slug', 'demo')->value('path')));
    }

    public function test_uploading_the_same_version_twice_is_refused(): void
    {
        $admin = $this->admin();
        $this->upload($admin, $this->package('1.0.0'));

        $response = $this->upload($admin, $this->package('1.0.0'));

        $response->assertStatus(422);
        $this->assertStringContainsString('уже загружена', $response->json('message'));
        $this->assertSame(1, Addon::where('slug', 'demo')->count());
    }

    /** Разные пакеты живут рядом — схлопывается только одинаковый slug. */
    public function test_different_packages_do_not_collide(): void
    {
        $admin = $this->admin();
        $this->upload($admin, $this->package('1.0.0', 'demo'));
        $this->upload($admin, $this->package('1.0.0', 'other'));

        $this->assertSame(2, Addon::whereIn('slug', ['demo', 'other'])->count());
    }
}
