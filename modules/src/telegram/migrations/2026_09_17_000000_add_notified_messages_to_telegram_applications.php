<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Запам'ятовує, кому і яким повідомленням прийшло сповіщення "НОВА
 * ЗАЯВКА" з кнопками ✅/✖️ — щоб після рішення прибрати ці кнопки з УСІХ
 * копій одразу, а не лише з тієї, по якій хтось реально натиснув.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_applications', function (Blueprint $table) {
            $table->json('notified_messages')->nullable()->after('review_note');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_applications', function (Blueprint $table) {
            $table->dropColumn('notified_messages');
        });
    }
};
