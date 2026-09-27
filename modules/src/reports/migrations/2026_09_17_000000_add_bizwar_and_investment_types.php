<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Додає два нових типи звіту — «бізвар» (перемога/поразка, як KAPT) та
 * «інвестиції» (із сумою). type був справжнім DB-enum (MySQL/MariaDB) —
 * розширювати список значень enum'а ALTER'ом надійно на різних драйверах
 * важко, тож переводимо колонку на звичайний string і далі валідуємо
 * список значень на рівні застосунку (як усюди в цьому проєкті, напр.
 * users.position_key). outcome лишається як є й повторно
 * використовується для bizwar — у нього той самий сенс win/loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('type', 20)->change();
            $table->unsignedBigInteger('amount')->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('amount');
            $table->enum('type', ['kapt', 'contract', 'other'])->change();
        });
    }
};
