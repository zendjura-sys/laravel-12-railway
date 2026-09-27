<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // nullOnDelete, не cascade: якщо повідомлення, на яке
            // відповіли, колись стане можна видаляти, сама відповідь не
            // повинна зникати разом з ним — лишиться просто без цитати.
            $table->foreignId('reply_to_message_id')->nullable()->after('sender_id')
                ->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_message_id');
        });
    }
};
