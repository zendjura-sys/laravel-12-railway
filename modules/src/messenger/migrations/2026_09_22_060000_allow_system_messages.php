<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sender_id NULL — системне повідомлення без живого відправника
        // (чат "Monsory Finance", conversations.type = 'finance'). Окремий
        // службовий користувач не підходить: він потрапив би в сімейний чат,
        // пошук учасників і рейтинги, а тіньовий акаунт з таким іменем
        // міг би "забрати" будь-хто при реєстрації.
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('messages')->whereNull('sender_id')->delete();

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable(false)->change();
        });
    }
};
