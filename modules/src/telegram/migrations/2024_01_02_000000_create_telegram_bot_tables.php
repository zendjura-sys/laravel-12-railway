<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Сессия бота. Навигация построена на редактировании ОДНОГО
        // сообщения, поэтому его message_id надо помнить между апдейтами:
        // Telegram сам состояние диалога не хранит.
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('telegram_username')->nullable();
            $table->string('first_name')->nullable();
            $table->unsignedBigInteger('menu_message_id')->nullable();
            // Не null только пока человек заполняет заявку: подсказывает,
            // как понимать следующее текстовое сообщение из этого чата.
            $table->string('step', 32)->nullable();
            $table->json('draft')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_applications', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->index();
            $table->string('telegram_username')->nullable();
            // Заявку подают ещё ДО регистрации на сайте, поэтому user_id
            // почти всегда null — он появляется, только если чат уже
            // привязан к аккаунту.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nickname');
            $table->string('age_range', 32);
            $table->string('playtime', 32);
            $table->string('experience', 32);
            $table->string('direction', 32);
            $table->text('about')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_applications');
        Schema::dropIfExists('telegram_chats');
    }
};
