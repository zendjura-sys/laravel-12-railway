<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // FCM-токен пристрою для мобільного push (MobilePushSender) —
            // окремий канал поруч із push_subscriptions (браузерний
            // Web Push) і TelegramLink. unique: той самий пристрій може
            // перелогінитись під іншим користувачем — старий запис просто
            // переприв'язується (updateOrCreate за token), а не дублюється.
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
