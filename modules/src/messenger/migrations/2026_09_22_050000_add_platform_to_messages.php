<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Звідки надіслано повідомлення — бейдж у чаті (📱/💻/🌐). null для
        // старих повідомлень (до цього релізу) — бейдж просто не
        // показується, заднім числом визначити платформу нема як.
        Schema::table('messages', function (Blueprint $table) {
            $table->string('platform')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
