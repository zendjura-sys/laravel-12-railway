<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // photo/sticker — файл на диску (attachment_path), gif —
            // зовнішнє посилання з Giphy (attachment_url). body лишається
            // NOT NULL (порожній рядок, якщо без підпису) — без
            // doctrine/dbal тут не можна безпечно змінити nullable.
            $table->string('type')->default('text')->after('body');
            $table->string('attachment_path')->nullable()->after('type');
            $table->string('attachment_url')->nullable()->after('attachment_path');
        });

        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            // Особиста бібліотека: кожен учасник керує лише своїми
            // стікерами (завантажив — бачить у власному пікері), але
            // надіслане зображення видно всім у розмові як звичайне
            // повідомлення.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stickers');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'attachment_path', 'attachment_url']);
        });
    }
};
