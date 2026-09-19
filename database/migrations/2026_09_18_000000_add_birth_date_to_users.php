<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Учасник вказує сам, за бажанням — нічого не показуємо, поки
            // не заповнено. TelegramBot (якщо встановлено) щодня перевіряє
            // місяць+день і вітає в сімейному чаті.
            $table->date('birth_date')->nullable()->after('position_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });
    }
};
