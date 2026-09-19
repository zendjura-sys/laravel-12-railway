<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Посада учасника в родині — окрема від ролей доступу (admin/etc), окрема
 * і від XP-«рангу» в модулі Progression.
 *
 * Зберігаємо ІНДЕКС у списку config('family.positions'), а не назву
 * текстом: якщо адмін перейменує посаду в Дизайн → Структура/Розділи,
 * прив'язка учасника лишиться коректною — вона показує N-ту позицію
 * списку, а не заморожений напис.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('position_index')->nullable()->after('last_name');
        });

        // Усі наявні акаунти — на старті родини, тож логічний дефолт той
        // самий, що обіцяє копірайт на сайті й у боті: "Починають усі
        // однаково — зі Стажера" (індекс 0).
        DB::table('users')->whereNull('position_index')->update(['position_index' => 0]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position_index');
        });
    }
};
