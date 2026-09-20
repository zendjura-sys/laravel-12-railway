<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
            'downloadUrl' => Setting::get('mobile_app_download_url'),
        ]);
    }
}
