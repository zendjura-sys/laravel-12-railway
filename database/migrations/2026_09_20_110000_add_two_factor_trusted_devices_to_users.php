<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Список хешів токенів "довірених" пристроїв (див.
            // App\Support\TwoFactorAuthentication::trustDevice()) — сирий
            // токен живе лише в cookie браузера, тут — тільки sha256 і
            // термін дії, щоб навіть витік бази не давав готового пропуска
            // повз 2FA.
            $table->json('two_factor_trusted_devices')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_trusted_devices');
        });
    }
};
