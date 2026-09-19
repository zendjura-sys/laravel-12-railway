<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('audience_type')->default('all')->after('pinned'); // all|role|position
            $table->string('audience_value')->nullable()->after('audience_type'); // назва ролі чи position_key
            // Знімок на момент відправки — аудит-слід "кому й скільки" не
            // залежить від того, чи існують ще ці юзери/notifications-рядки
            // пізніше. Окремої audit-таблиці не заводимо: цей рядок сам по
            // собі вже і є повний запис хто/коли/кому/скільки (хто —
            // created_by, коли — created_at), а статуси Telegram-доставки
            // рахуються з broadcast_deliveries.
            $table->unsignedInteger('recipients_count')->default(0)->after('audience_value');
        });

        // Per-recipient статус Telegram-доставки. Web-копія (notifications)
        // лишається окремо — вона завжди миттєва (звичайний insert), а тут
        // тільки те, що реально йде в чергу й може впасти/зачекати ретраю.
        Schema::create('broadcast_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_deliveries');
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['audience_type', 'audience_value', 'recipients_count']);
        });
    }
};
