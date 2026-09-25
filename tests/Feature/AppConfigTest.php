<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use RefreshDatabase;


    public function test_relative_download_url_is_resolved_to_absolute(): void
    {
        Setting::set('mobile_app_download_url', '/downloads/monsory-connect.apk', 'mobile_app');

        $this->getJson('/api/app-config')
            ->assertOk()
            ->assertJson([
                'downloadUrl' => url('/downloads/monsory-connect.apk'),
            ]);
    }

    public function test_absolute_download_url_is_kept_as_is(): void
    {
        Setting::set('mobile_app_download_url', 'https://github.com/example/releases/app.apk', 'mobile_app');

        $this->getJson('/api/app-config')
            ->assertOk()
            ->assertJson([
                'downloadUrl' => 'https://github.com/example/releases/app.apk',
            ]);
    }

    public function test_empty_download_url_is_null(): void
    {
        Setting::set('mobile_app_download_url', '', 'mobile_app');

        $this->getJson('/api/app-config')
            ->assertOk()
            ->assertJson([
                'downloadUrl' => null,
            ]);
    }
}
