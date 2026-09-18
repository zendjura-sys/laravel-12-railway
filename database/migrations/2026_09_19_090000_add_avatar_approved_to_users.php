<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Нове фото за замовчуванням "на перевірці" — на головну
            // потрапляє лише після того, як адмін гляне і підтвердить у
            // Дизайні. Без цього самостійне завантаження в профілі йшло б
            // прямо на публічну головну, без жодної модерації.
            $table->boolean('avatar_approved')->default(false)->after('avatar_path');
        });

        // Фото, які вже стояли до цієї міграції, вже й так були видні на
        // сайті — не ховаємо їх раптово без причини, підтверджуємо разом.
        DB::table('users')->whereNotNull('avatar_path')->update(['avatar_approved' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_approved');
        });
    }
};
