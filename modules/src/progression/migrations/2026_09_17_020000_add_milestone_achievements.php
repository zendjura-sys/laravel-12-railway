<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Лічильники для нових рубежів: скільки звітів БУДЬ-ЯКОГО типу
        // затверджено (не лише kapt/contract, як інші поля профілю) і
        // скільки всього вкладено інвестицій — жодне з цього ще не
        // накопичувалось окремо.
        Schema::table('progression_profiles', function (Blueprint $table) {
            $table->unsignedInteger('reports_total')->default(0)->after('heavy_contracts_count');
            $table->unsignedInteger('investment_total')->default(0)->after('reports_total');
        });

        $now = now();
        DB::table('achievements')->insert([
            ['code' => 'reports_10', 'name' => '10 звітів', 'description' => '10 затверджених звітів будь-якого типу', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'reports_50', 'name' => '50 звітів', 'description' => '50 затверджених звітів будь-якого типу', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'reports_100', 'name' => '100 звітів', 'description' => '100 затверджених звітів будь-якого типу', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'investments_10000', 'name' => 'Інвестор: 10 000₴', 'description' => 'Сукупно вкладено 10 000₴ інвестицій', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'investments_50000', 'name' => 'Інвестор: 50 000₴', 'description' => 'Сукупно вкладено 50 000₴ інвестицій', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'investments_100000', 'name' => 'Інвестор: 100 000₴', 'description' => 'Сукупно вкладено 100 000₴ інвестицій', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'telegram_linked', 'name' => 'На звʼязку', 'description' => 'Привʼязано Telegram до акаунту', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('achievements')->whereIn('code', [
            'reports_10', 'reports_50', 'reports_100',
            'investments_10000', 'investments_50000', 'investments_100000',
            'telegram_linked',
        ])->delete();

        Schema::table('progression_profiles', function (Blueprint $table) {
            $table->dropColumn(['reports_total', 'investment_total']);
        });
    }
};
