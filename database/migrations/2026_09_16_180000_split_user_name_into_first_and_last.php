<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Имя и фамилия отдельными полями.
 *
 * Колонку name НЕ удаляем: на неё завязано всё отображение — приветствие
 * в кабинете, списки участников, подписи в Telegram-боте, уведомления
 * Laravel. Она остаётся производной и пересобирается из first_name и
 * last_name в модели, так что источник правды один, а вся старая логика
 * продолжает работать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Переносим то, что уже введено: всё до первого пробела — имя,
        // остаток — фамилия. У ников без пробела фамилия останется пустой,
        // это нормально: человек допишет её сам в профиле.
        foreach (DB::table('users')->select('id', 'name')->get() as $user) {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
