<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Мобільний застосунок якийсь час качав оновлення напряму з github.com —
 * і в частини користувачів це провалювалось (GitHub недоступний/обрізається
 * на їхній мобільній мережі). Ці маршрути проксіюють той самий GitHub
 * Release через наш сервер, тож перевіряємо лише проксіювання, не сам
 * GitHub — реальний мережевий виклик підмінюємо.
 */
class MobileDownloadTest extends TestCase
{
    public function test_build_number_is_proxied_from_github(): void
    {
        Http::fake([
            '*/releases/latest/download/build.txt' => Http::response('42', 200),
        ]);

        $this->get('/downloads/mobile-build.txt')
            ->assertOk()
            ->assertSee('42');
    }

    public function test_apk_download_is_proxied_from_github(): void
    {
        Http::fake([
            '*/releases/latest/download/monsory-connect.apk' => Http::response('fake-apk-bytes', 200, [
                'Content-Type' => 'application/vnd.android.package-archive',
            ]),
        ]);

        $response = $this->get('/downloads/mobile-apk');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        $this->assertStringContainsString('fake-apk-bytes', $response->streamedContent());
    }
}
