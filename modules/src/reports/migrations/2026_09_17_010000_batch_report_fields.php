<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бізвар і Контракт стають пакетним звітом за дату (кількості за раз),
 * а не одним записом на одну подію: раніше bizwar/contract фіксували
 * одну перемогу/поразку чи одну вагу контракту на звіт, тепер — скільки
 * саме перемог/поразок і скільки контрактів кожної ваги набралось за
 * дату, плюс час капта (масив — множинний вибір) для майбутнього модуля
 * автопідрахунку премій.
 *
 * Старі колонки (outcome, weight) НЕ чіпаємо — вони й далі коректно
 * описують історичні kapt/contract-звіти, подані до цієї зміни.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->date('report_date')->nullable()->after('type');
            $table->unsignedInteger('wins_count')->nullable()->after('outcome');
            $table->unsignedInteger('losses_count')->nullable()->after('wins_count');
            $table->json('kapt_times')->nullable()->after('losses_count');
            $table->unsignedInteger('light_count')->nullable()->after('weight');
            $table->unsignedInteger('medium_count')->nullable()->after('light_count');
            $table->unsignedInteger('heavy_count')->nullable()->after('medium_count');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'report_date', 'wins_count', 'losses_count', 'kapt_times',
                'light_count', 'medium_count', 'heavy_count',
            ]);
        });
    }
};
