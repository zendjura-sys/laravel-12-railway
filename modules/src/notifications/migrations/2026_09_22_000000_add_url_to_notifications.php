<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Кнопка-посилання (telegramButton) уже йшла в Telegram і в
        // web/мобільний push, але сама web-копія (notifications) її не
        // зберігала — тап на сповіщення в списку ("дзвіночок") ніколи
        // нікуди не вів, лише позначав прочитаним.
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('url')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
