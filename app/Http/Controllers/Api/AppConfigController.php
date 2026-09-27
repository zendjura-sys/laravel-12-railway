<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\DesignSettings;
use Illuminate\Http\JsonResponse;

/**
 * Публічна (без auth:sanctum) конфігурація мобільного застосунку —
 * читається ще ДО логіну, щоб показати "на обслуговуванні" чи "онови
 * застосунок" замість екрана входу. Той самий Setting::get(), що й решта
 * адмінських налаштувань (Admin → Налаштування → Мобільний застосунок).
 */
class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'enabled' => Setting::get('mobile_app_enabled') !== '0',
            'maintenanceMessage' => Setting::get('mobile_app_maintenance_message'),
            'minBuild' => (int) (Setting::get('mobile_app_min_build') ?? 0),
            // На відміну від minBuild (жорсткий блок — старіше не пускає
            // далі логіну), це м'яке "є новіша версія" — застосунок
            // працює як завжди, просто раз пропонує оновитись.
            'latestBuild' => (int) (Setting::get('mobile_app_latest_build') ?? 0),
            'updateMessage' => Setting::get('mobile_app_update_message'),
            'bankTabEnabled' => Setting::get('mobile_app_bank_enabled') !== '0',
            'leaderboardTabEnabled' => Setting::get('mobile_app_leaderboard_enabled') !== '0',
            'reportsTabEnabled' => Setting::get('mobile_app_reports_enabled') !== '0',
            // Адмін може вписати як повний URL (https://...), так і
            // відносний шлях (/downloads/monsory-connect.apk, як ставить
            // deploy/setup-vps.sh) — телефон не має поняття "поточний
            // домен", тому відносний шлях тут же перетворюємо на повний,
            // інакше Uri.parse() у застосунку не матиме хоста для запиту.
            'downloadUrl' => $this->absoluteDownloadUrl(Setting::get('mobile_app_download_url')),
            // Палітра керується з сайту (Admin → Дизайн → Оформлення) —
            // застосунок фарбує свою gold-гаму цими ж відтінками, тому
            // зміна бренд-кольору на сайті одразу видно й у застосунку,
            // без нової збірки APK. null, поки колір дефолтний: формула
            // змішування лише наближає оригінальні hand-picked відтінки
            // (як і на сайті — accentCss() так само мовчить за дефолту),
            // тож застосунок тоді лишається на своїх точних дефолтних
            // кольорах, а не трохи інакших "приблизних".
            'accent' => DesignSettings::accent(),
            'accentShades' => DesignSettings::accent() === DesignSettings::DEFAULT_ACCENT
                ? null
                : DesignSettings::accentShades(),
        ]);
    }

    private function absoluteDownloadUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
